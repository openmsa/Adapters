<?php
/*
 * Created: May 23, 2022
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/net_common.php';
require_once 'smsd/pattern.php';

require_once load_once('arista_eos', 'common.php');
require_once load_once('arista_eos', 'adaptor.php');
require_once load_once('arista_eos', 'arista_eos_connection.php');
require_once load_once('arista_eos', 'arista_eos_apply_conf.php');
require_once "$db_objects";

/**
 * @addtogroup aristaupdate  Arista Routers Update Process
 * @{
 */

/** Main configuration manager
 * All the profile managed are listed in the constructor.
 */
class AristaEosConfiguration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $revision_id;
  var $net_conf;
  var $sd;
  var $fmc_repo;
  var $conf_pflid;

  // ------------------------------------------------------------------------------------------------
  /**
	* Constructor
	* The list of all the managed profiles is created here.
	* @param $sdid    SD ID of the current SD to configure
	* @param $is_provisionning    true if the update is for provisioning (more initializations)
	*/
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->net_conf = get_network_profile();
    $this->sd = $this->net_conf->SD;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
  }

  function get_running_conf()
  {
    global $sms_sd_ctx;

    $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show run | exc Last configuration change");
    if (!empty($running_conf))
    {
      // trimming first and last lines
      $pos = strpos($running_conf, 'Current configuration');
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, $pos);
      }
      // remove 'ntp clock-period' line
      $running_conf = remove_end_of_line_starting_with($running_conf, 'Current configuration');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'ntp clock-period');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'enable secret 5');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create cnf-files version-stamp');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'Current configuration :');
      $pos = strrpos($running_conf, "\n");
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, 0, $pos + 1);
      }
    }

    $this->running_conf = $running_conf;
    return $this->running_conf;
  }

  function get_preconf()
  {
    $conf_start = PATTERNIZETEMPLATE('snmp_conf.tpl');
    $conf_start .= PATTERNIZETEMPLATE('syslog_conf.tpl');
    return $conf_start;
  }

  // ------------------------------------------------------------------------------------------------
  /**
	* Store the running configuration for further deltas
	*/
  function store_running($running_conf)
  {
    $this->running_conf = $running_conf;
    return SMS_OK;
  }

  function get_current_firmware_name()
  {
    global $sms_sd_ctx;

    // Grab current firmware file name
    $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show ver | inc image file");
    $current_firmware = substr($line, strpos($line, '"') + 1, strrpos($line, '"') - strpos($line, '"') - 1);

    return $current_firmware;
  }

  // ------------------------------------------------------------------------------------------------
  /**
	*
	*/
  /*function build_conf()
	{
	    $generated_configuration = $this->generate_begin_conf();
	    $generated_configuration .= $this->generate();
	    $generated_configuration .= $this->generate_end_conf();
	    return $generated_configuration;
	}*/

  // function to be called after the configuration transfer
  function copy_to_running($conn, $cmd)
  {
    global $sdid;
    //global $sms_sd_ctx;
    //global $sendexpect_result;


    $tab[0] = $conn->getPrompt(); //$sms_sd_ctx->getPrompt();
    $tab[1] = '[no]:';
    $tab[2] = ']?';
    $tab[3] = '[confirm]';
    $tab[4] = '[yes/no]';
    $tab[5] = '#'; // during provisionning prompt can change
    $index = 1;
    $SMS_OUTPUT_BUF = '';
    for ($i = 1; ($i <= 10) && ($index !== 0); $i++)
    {
      $conn->send(__FILE__ . ':' . __LINE__, $cmd);
      $index = $conn->expect(__FILE__ . ':' . __LINE__, $tab, 500000);
      $SMS_OUTPUT_BUF .= $sendexpect_result;
      switch ($index)
      {
        case 1:
          if (strpos($sendexpect_result, 'Dynamic mapping in use') !== false)
          {
            $cmd = "yes";
          }
          elseif (strpos($sendexpect_result, 'Saving this config to nvram') !== false)
          {
            #% Warning: Saving this config to nvram may corrupt any network management or security files stored at the end of nvram.
            #encounter during restore on a device....
            $cmd = "yes";
          }
          else
          {
            save_result_file($SMS_OUTPUT_BUF, "conf.error");
            sms_log_error("$sdid:" . __FILE__ . ':' . __LINE__ . ": [[!!! $sendexpect_result !!!]]\n");
            $conn->send(__FILE__ . ':' . __LINE__, "\n");
            throw new SmsException("copy configuration to running error: " . $sendexpect_result, ERR_SD_CMDFAILED);
          }
          break;
        case 2:
          $cmd = '';
          break;
        case 3:
          $conn->send(__FILE__ . ':' . __LINE__, "\n");
          $cmd = '';
          break;
        case 4:
          $cmd = 'yes';
          break;
        case 5:
          $connection->do_store_prompt();
          $index = 0;
          break;
        default:
          $index = 0;
          break;
      }
    } // loop while the router is asking questions


    return $SMS_OUTPUT_BUF;
  }

  function apply_conf_by_tftp($connection, $configuration, $reconnect = true)
  {
    global $sdid;
    global $sms_sd_info;
    global $sms_sd_ctx;

    $file_name = "$sdid.cfg";
    $in_error = false;

    $check = trim($configuration);
    if (empty($check))
    {
      return SMS_OK;
    }

    $sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];

    // Save the configuration applied on the router
    save_result_file($configuration, 'conf.applied');

    // Create the file
    $local_file_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;
    $ret = save_file($configuration, $local_file_name);

    if ($ret !== SMS_OK)
    {
      throw new SmsException("TFTP Mode, saving configuration file [" . $local_file_name . "] for tftp transfert failed!", $ret);
    }

    $sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];

    $connection->sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/$file_name running-config", "]?");

    $SMS_OUTPUT_BUF = copy_to_running('');
    save_result_file($SMS_OUTPUT_BUF, "conf.error");

    foreach ($apply_errors as $apply_error)
    {
      if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
      {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
        return ERR_SD_CMDFAILED;
      }
    }

    $ret = func_write();

    return $ret;
  }

  function apply_conf_by_lines($connection, $configuration)
  {
    global $sdid;
    global $sms_sd_info;
    global $SMS_OUTPUT_BUF;

    $sendexpect_result = "";

    // ---------------------------------------------------
    // Line by line mode configuration
    // ---------------------------------------------------
    unset($tab);
    $tab[0] = "(config)#";
    $connection->send(__FILE__ . ':' . __LINE__, "conf t\n");
    $connection->expect(__FILE__ . ':' . __LINE__, $tab);

    unset($tab);
    $tab[0] = $connection->getPrompt();
    $tab[1] = ")#";
    $tab[2] = "]?";
    $tab[3] = "[confirm]";
    $tab[4] = "[no]:";

    save_result_file($configuration, 'conf.applied');

    $line = get_one_line($configuration);
    while ($line !== false)
    {
      if (strpos($line, "!") === 0)
      {
        echo "$sdid: $line\n";
      }
      else
      {
        $connection->send(__FILE__ . ':' . __LINE__, $line . "\n");
        $index = $connection->expect(__FILE__ . ':' . __LINE__, $tab);
        $SMS_OUTPUT_BUF .= $sendexpect_result;
        if (($index === 2) || ($index === 3))
        {
          $connection->send(__FILE__ . ':' . __LINE__, "\n");
          $connection->expect(__FILE__ . ':' . __LINE__, $tab);
          $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
        else if ($index === 4)
        {
          $connection->send(__FILE__ . ':' . __LINE__, "yes\n");
          $connection->expect(__FILE__ . ':' . __LINE__, $tab);
          $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
      }
      $line = get_one_line($configuration);
    }

    save_result_file($SMS_OUTPUT_BUF, 'conf.error');

    // resync prompt
    unset($tab);
    $tab[0] = "(config-ext-nacl)#";
    $connection->send(__FILE__ . ':' . __LINE__, "ip access-list extended NETCELO_FROM_INTERNET\n");
    $connection->expect(__FILE__ . ':' . __LINE__, $tab);

    // Exit from config mode
    unset($tab);
    $tab[0] = $connection->getPrompt();
    $tab[1] = ")#";

    $connection->send(__FILE__ . ':' . __LINE__, "\n");
    $index = $connection->expect(__FILE__ . ':' . __LINE__, $tab);

    for ($i = 1; ($i <= 10) && ($index === 1); $i++)
    {
      $connection->send(__FILE__ . ':' . __LINE__, "exit\n");
      $index = $connection->expect(__FILE__ . ':' . __LINE__, $tab);
    }

    unset($tab);
    $tab[0] = "Overwrite file";
    $tab[1] = $connection->getPrompt();

    $connection->send(__FILE__ . ':' . __LINE__, "write mem\n");
    $index = $connection->expect(__FILE__ . ':' . __LINE__, $tab);
    if ($index === 0)
    {
      $connection->sendexpectone(__FILE__ . ':' . __LINE__, "Yes");
    }
    return SMS_OK;
  }

  function apply_conf($connection, $configuration)
  {
    global $sdid;
    global $sms_sd_info;

    $file_name = "$sdid.cfg";

    $line_config_mode = $SD->SD_CONFIG_STEP;
    $line_config_mode = 1; // Dont allow yet the TFTP mode:


    if ($line_config_mode === 1)
    {
      return $this->apply_conf_by_lines($connection, $configuration);
    }
    else
    {
      return $this->apply_conf_by_tftp($connection, $configuration);
    }
  }

  // ------------------------------------------------------------------------------------------------
  /**
	*
	*/
  function update_conf()
  {
    $ret = $this->build_conf($generated_configuration);
    if (!empty($generated_configuration))
    {
        $ret = arista_eos_apply_conf($generated_configuration);
    }

    return $ret;
  }

  function provisioning()
  {
    return $this->update_conf();
  }

  // ------------------------------------------------------------------------------------------------
  /**
	*
	*/

  // -----------------  FIRMWARE ------------------------------ //
  function update_firmware($param = '')
  {
    global $sms_sd_ctx;
    global $status_message;
    global $is_local_file_server;

    init_local_file_server();

    status_progress('Checking firmware file', 'FIRMWARE');

    if (strpos($param, "FILE=") !== false)
    {
      if (preg_match("/FILE=(?<filename>.*)$/", $param, $matches) > 0)
      {
        $firmware_file = $matches['filename'];
      }
      else
      {
        return ERR_NO_FIRMWARE;
      }
    }
    else // use the repository
    {
      // Get firmware
      $ret = get_repo_files_map($map_conf, $error, 'Firmware');
      if ($ret !== SMS_OK)
      {
        // xml entity file broken
        return $ret;
      }
      if (!empty($map_conf))
      {
        foreach ($map_conf as $mkey => $repo_file)
        {
          if (!empty($repo_file))
          {
            $firmware_file = "{$this->fmc_repo}/$repo_file";
            break; // use this first file found
          }
        }
      }
    }
    if (empty($firmware_file))
    {
      sms_log_info(__FUNCTION__ . ": no file specified.\n");
      $status_message = "No file specified";
      return SMS_OK;
    }

    if (!$is_local_file_server)
    {
      // Check if file exists only on SOC
      clearstatcache(TRUE, $firmware_file);
      if (!file_exists($firmware_file))
      {
        return ERR_NO_FIRMWARE;
      }
    }
    else
    {
      status_progress('Checking local file server synchronization', 'FIRMWARE');
      $timeout_synchro = intval($_SERVER['LOCAL_SERVER_SYNCHRO_TIMEOUT']);
      if ($timeout_synchro == 0)
      {
        $timeout_synchro = 300;
      }
      $is_synchronized = false;
      while (!$is_synchronized)
      {
        $res = get_file_synchro_status($repo_file);
        if ($res === 'SYNCHRONIZED')
        {
          $is_synchronized = true;
        }
        else
        {
          if ($res === 'ERROR')
          {
            return ERR_LOCAL_FILE;
          }
          status_progress('Waiting for file synchronization', 'FIRMWARE');
          $timeout_synchro -= 30;
          if ($timeout_synchro < 0)
          {
            $status_message = "Firmware file not synchronized on local server";
            return ERR_LOCAL_FILE;
          }
          sleep(30);
        }
      }
    }

    status_progress('Checking installed firmware', 'FIRMWARE');
    // Check if the firmware is currently installed (flash:...)
    $previous_firmware = $this->get_current_firmware_name();
    if (basename($firmware_file) == preg_replace('@flash:@', '', $previous_firmware))
    {
      $status_message = 'Firmware already installed';
      return SMS_OK;
    }

    status_progress('Checking available space on the router', 'FIRMWARE');
    // Get file size
    $firmware_size = filesize($firmware_file);

    // Check size
    $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show flash: | inc total");
    if ($line !== false)
    {
      if (preg_match('/(?<mem>\d+)( bytes)? free/', $line, $matches) > 0)
      {
        $flash_avail = $matches['mem'];
      }
    }

    if (empty($flash_avail) || $flash_avail < $firmware_size)
    {
      // not enough memory on device
      return ERR_SD_ENOMEM;
    }

    status_progress('Transfering firmware file (TFTP)', 'FIRMWARE');
    // Transfer firmware with tftp
    if ($is_local_file_server)
    {
      $src = $repo_file;
    }
    else
    {
      $src = $firmware_file;
    }
    $dst = basename($firmware_file);
    $ret = send_file_to_router($src, $dst);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // Verify flash command seems to works not correcly
    status_progress('Configuring boot file', 'FIRMWARE');
    // Configure new firmware
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#");
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "no boot system", "(config)#");
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "boot system flash:$dst", "(config)#");
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", "#");

    $ret = func_write();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // reboot
    if (strpos($param, "NO_REBOOT") === false)
    {
      status_progress('Reloading device', 'FIRMWARE');

      func_reboot('update firmware');
      arista_eos_disconnect();
      sleep(70);
      $ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
      if ($ret != SMS_OK)
      {
        return $ret;
      }
      status_progress('Connecting to the device', 'FIRMWARE');

      $ret = arista_eos_connect();
      if ($ret != SMS_OK)
      {
        return $ret;
      }
      status_progress('Checking the firmware currently used', 'FIRMWARE');

      // Check that the device booted on the new firmware
      $new_firmware = $this->get_current_firmware_name();
      if (basename($firmware_file) !== preg_replace('@flash:@', '', $new_firmware))
      {
        return ERR_FIRMWARE_CORRUPTED;
      }
      status_progress('Removing previous firmware file', 'FIRMWARE');

      // Remove previous firmware file
      unset($tab);
      $tab[0] = '#';
      $tab[1] = ']?';
      $tab[2] = '[confirm]';
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete $previous_firmware", $tab);
      while ($index > 0)
      {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
      }
    }

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
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

  // ------------------------------------------------------------------------------------------------
  /**
	* Generate a full configuration
	 * Uses the previous conf if present to perform deltas
	 */
  function generate(&$configuration, $use_running = false)
  {
    //$configuration .= "! CONFIGURATION GOES HERE\n";
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
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

  // ------------------------------------------------------------------------------------------------
  /**
	*
	*/
  function build_conf(&$generated_configuration)
  {
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

    $ret = $this->generate_post_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }

  public function reboot($reconnect = true)
  {
    echo "***Call Arista EOS Reboot***\n";
    unset($tab);
    $tab[0] = "Resource is temporarily unavailable - try again later";
    $tab[1] = "[confirm]";
    $tab[2] = "Shutting down";

    $this->send(__FILE__ . ':' . __LINE__, "reload\n");
    $choice = $this->expect(__FILE__ . ':' . __LINE__, $tab);

    while ($choice !== 2)
    {
      switch ($choice)
      {
        case 0:
          sleep(2);
          $this->sendCmd(__FILE__ . ':' . __LINE__, "reload");
          $choice = $this->expect(__FILE__ . ':' . __LINE__, $tab);
          break;
        case 1:
          $this->sendCmd(__FILE__ . ':' . __LINE__, "");
          $choice = 2;
          break;
        case 2:
          echo "Device reboot ongoing\n";
          break;
        default:
          throw new SmsException("Failed to reboot device!", ERR_SD_CMDFAILED);
      }
    }

    // From here, we loose connection !
    if ($reconnect === true)
    {
      echo "Waiting for device up ...\n";
      wait_for_device_up($this->sd_ip_config, $nb_loop = 60);
      $this->do_connect();
    }
  }
}

/**
 * @}
 */

?>
