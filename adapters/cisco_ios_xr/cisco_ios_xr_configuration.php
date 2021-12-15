<?php

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('cisco_ios_xr', 'common.php');
require_once load_once('cisco_ios_xr', 'adaptor.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_apply_conf.php');
require_once "$db_objects";

class cisco_ios_xr_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $profile_list; // List of managed profiles
  var $previous_conf_list; // Previous generated configuration loaded from files
  var $conf_list; // Current generated configuration waiting to be saved
  var $addon_list; // List of managed addon cards
  var $fmc_repo; // repository path without trailing /
  var $sd;

  // ------------------------------------------------------------------------------------------------
  /**
    * Constructor
    */
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $net = get_network_profile();
    $this->sd = &$net->SD;
    $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
  }

  // ------------------------------------------------------------------------------------------------
  /**
    * Get running configuration from the router
    */
  function get_running_conf(&$running_conf)
  {
    global $sendexpect_result;
    global $apply_errors;
    global $default_disk;

    global $sms_sd_ctx;
    $ret = SMS_OK;

    if ($sms_sd_ctx == null)
    {
        return ERR_SD_FAILED;
    }

    // destination for configuration file on device
    $src_disk = $default_disk;

    $file_name = "{$this->sdid}.cfg";
    $full_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;
    $fname_on_device = "read_$file_name";

    echo "Save configuration\n";
    $ERROR_BUFFER = '';

    $line = "delete /noprompt $src_disk:$fname_on_device";
    $SMS_OUTPUT_BUF = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, "#", DELAY);

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#", DELAY);

    /*
    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#save configuration running disk0:read_UBI153.cfg
    Destination file name (control-c to abort): [/read_UBI153.cfg]?
    Building configuration.
    231 lines built in 1 second
    [OK]
    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#save configuration running disk0:read_UBI153.cfg
    Destination file name (control-c to abort): [/read_UBI153.cfg]?
    The destination file already exists. Do you want to overwrite? [no]: yes
    Building configuration.
    231 lines built in 1 second
    [OK]
    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#
    */

    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = ")#";
    $tab[2] = $fname_on_device."]?";
    $tab[3] = "overwrite? [no]:";

    $line = "save configuration running $src_disk:$fname_on_device";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
    $SMS_OUTPUT_BUF = $sendexpect_result;

    while (($index !== 0) && ($index !== 1))
    {
        if ($index === 2)
        {
            $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, DELAY);
            $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
        else if ($index === 3)
        {
            $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "yes", $tab, DELAY);
            $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
    }
    foreach ($apply_errors as $apply_error)
    {
        if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0)
        {
            $ERROR_BUFFER .= "!";
            $ERROR_BUFFER .= "\n";
            $ERROR_BUFFER .= $line;
            $ERROR_BUFFER .= "\n";
            $ERROR_BUFFER .= $apply_error;
            $ERROR_BUFFER .= "\n";

            sms_log_error ( __FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n" );
            $SMS_OUTPUT_BUF = '';
            $ret = ERR_SD_CMDFAILED;
        }
    }

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", "#", DELAY);

    if ($ret === SMS_OK)
    {
        try {
            if (file_exists($full_name))
            {
                unlink($full_name);
            }
            $ret = scp_from_router ( "$src_disk:$fname_on_device", $full_name );
            if ($ret === SMS_OK) {
                $running_conf = file_get_contents($full_name);
            }
            else {
                sms_log_error ( __FILE__ . ':' . __LINE__ . ": scp_from_router($src_disk:$fname_on_device, $full_name) FAILED\n" );
            }
        } catch ( Exception | Error $e ) {
            sms_log_error ( __FILE__ . ':' . __LINE__ . ": SCP Exception/Error: " . $e->getMessage . "\n" );
            if (strpos ( $e->getMessage (), 'connection failed' ) !== false) {
                return ERR_SD_CONNREFUSED;
            }
            return ERR_SD_SCP;
        }
    }

    if (!empty($running_conf))
    {
      // trimming first and last lines
      $pos = strpos($running_conf, 'Building configuration...');
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, $pos);
      }
      // remove 'ntp clock-period' line
      $running_conf = remove_line_starting_with($running_conf, 'Building configuration...');
      $running_conf = remove_line_starting_with($running_conf, '!! IOS XR Configuration version');
      $running_conf = remove_line_starting_with($running_conf, '!! Last configuration change at');
      $running_conf = remove_line_starting_with($running_conf, 'ntp clock-period');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' secret 5');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create profile sync');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' password 7');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create cnf-files version-stamp');
      $pos = strrpos($running_conf, "\n");
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, 0, $pos + 1);
      }
    }

    $this->running_conf = $running_conf;
    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
    * Generate a configuration cleaner based on the current router configuration
    */
  function generate_clean(&$configuration)
  {
    $ret = SMS_OK;

    // Load router conf if necessary
    if (empty($this->running_conf))
    {
      $ret = $this->get_running_conf($this->running_conf);
    }

    if ($ret === SMS_OK)
    {
        foreach ($this->profile_list as $profile_name => $profile)
        {
          $parser = $profile->get_parser_clean();
          $parsed_running = parse_conf($this->running_conf, $parser);
          $delta_conf = conf_differ($parsed_running, null);
          // Generate the configuration from the delta
          $configuration .= "! Clean $profile_name Configuration -- \n!\n";
          $configuration .= generate_conf_from_diff($delta_conf, $parser);
          $configuration .= "!\n! END Clean $profile_name Configuration\n!\n";
        }
    }
    else
    {
        sms_log_error ( __FILE__ . ':' . __LINE__ . ": get_running_conf() FAILED\n" );
    }

    return $ret;
  }

  function get_current_firmware_name()
  {
    global $sms_sd_ctx;

    // Grab current firmware file name
    $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show ver | inc image file");
    $current_firmware = substr($line, strpos($line, '"') + 1, strrpos($line, '"') - strpos($line, '"') - 1);

    return $current_firmware;
  }

  /**
    * Generate the general pre-configuration
    * @param $configuration   configuration buffer to fill
    */
  function generate_pre_conf(&$configuration)
  {
    //$configuration .= "!PRE CONFIG\n";
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_CONFIG', 'Configuration');
    return SMS_OK;
  }

  /**
   * Generate the configuration based on tag name
   * @param $configuration   configuration buffer to fill
   * @param $tag_name   name of the configuration to be generated
   */
   function generate_conf(&$configuration,$tag_name)
   {
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, $tag_name, 'Configuration');
    $ret = $this->generate_profile_conf($configuration);
        if ($ret !== SMS_OK)
        {
        return $ret;
    }
    return SMS_OK;
    }

  /**
     * Generate a full configuration
     * Uses the previous conf if present to perform deltas
     */
  function generate(&$configuration, $use_running = false)
  {
    //$configuration .= "! CONFIGURATION GOES HERE\n";
    $configuration .= '';
    $ret = $this->generate_profile_conf($configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
    * Generate profile configuration
    * Uses the previous conf if present to perform deltas
    */
  function generate_profile_conf(&$configuration, $use_running = false)
  {
    if (!empty($this->profile_list))
    {
      foreach ($this->profile_list as $profile_name => $profile)
      {
        if ($profile->is_active())
        {
          $parser = $profile->get_parser();
          if ($use_running)
          {
            $parsed_previous = parse_conf($this->running_conf, $parser);
            $profile->parse_running_conf($this->running_conf);
          }
          else
          {
            $parsed_previous = parse_conf($this->previous_conf_list[$profile_name], $parser);
            $profile->parse_running_conf($this->previous_conf_list[$profile_name]);
          }

          $pre_conf = '';
          $profile->get_pre_conf($pre_conf);

          $conf = '';
          $profile->get_conf($conf);
          // keep the conf in order to save it later
          $this->conf_list[$profile_name] = $conf;
          $parsed_generated = parse_conf($conf, $parser);
          // Compare configurations
          $delta_conf = conf_differ($parsed_previous, $parsed_generated);

          $post_conf = '';
          $profile->get_post_conf($post_conf, $delta_conf);

          // Generate the configuration from the delta
          $configuration .= "! $profile_name Configuration -- \n!\n";
          $configuration .= $pre_conf;
          $configuration .= generate_conf_from_diff($delta_conf, $parser);
          $configuration .= $post_conf;
          $configuration .= "!\n! END $profile_name Configuration\n!\n";
        }
        else
        {
          echo "$profile_name Profile is not active\n";
        }
      }
    }
    return SMS_OK;
  }

  /**
    * Generate the general post-configuration
    * @param $configuration   configuration buffer to fill
    */
  function generate_post_conf(&$configuration)
  {
    //$configuration .= "!POST CONFIG\n";
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
    return SMS_OK;
  }


  function build_conf(&$generated_configuration)
  {
    //$this->monitoring_conf($generated_configuration);
    $ret = $this->generate_pre_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = $this->generate($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $ret = $this->generate_conf($generated_configuration,'ZTD_TEMPLATE');
    if ($ret !== SMS_OK)
    {
        return $ret;
    }

    $ret = $this->generate_post_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }

  function update_conf($flag = false)
  {
    $ret = $this->build_conf($generated_configuration);

    if (!empty($generated_configuration))
    {
      $ret = cisco_ios_xr_apply_conf($generated_configuration,$flag);
    }
    return $ret;
  }

  function provisioning($flag = false)
  {
    return $this->update_conf($flag);
  }




  function reboot($event, $params = '')
  {
    status_progress('Reloading device', $event);

    func_reboot($event);
    cisco_ios_xr_disconnect();
    sleep(70);
    $ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
    if ($ret != SMS_OK)
    {
      return $ret;
    }
    status_progress('Connecting to the device', $event);

    $loop = 5;
    while ($loop > 0)
    {
      sleep(10); // wait for ssh to come up
      $ret = cisco_ios_xr_connect();
      if ($ret == SMS_OK)
      {
        break;
      }
      $loop--;
    }

    return $ret;
  }

  function delete_router_file($event, $file)
  {
    global $sms_sd_ctx;

    status_progress('Connecting to the device', $event);

    $ret = cisco_ios_xr_connect();

    if ($ret != SMS_OK)
    {
        return $ret;
    }

    status_progress('Deleting router file', $event);

    // Remove previous firmware file
    unset($tab);
    $tab[0] = 'Error';
    $tab[1] = 'File not found';
    $tab[2] = '#';
    $tab[3] = ']?';
    $tab[4] = '[confirm]';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete $file", $tab);
    while ($index > 2)
    {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
        if ($index < 2)
        {
            return ERR_LOCAL_FILE;
        }
    }

    return SMS_OK;
  }
}

?>