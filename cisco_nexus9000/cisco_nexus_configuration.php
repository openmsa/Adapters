<?php
/*
 * Version: $Id: cisco_nexus_configuration.php 58927 2012-06-11 15:15:18Z abr $
* Created: Feb 12, 2009
*/
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('cisco_nexus9000', 'common.php');
require_once load_once('cisco_nexus9000', 'adaptor.php');
require_once load_once('cisco_nexus9000', 'cisco_nexus_apply_conf.php');
require_once "$db_objects";

class cisco_nexus_configuration
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
  function get_running_conf()
  {
    global $sms_sd_ctx;

    if ($sms_sd_ctx != null)
    {
      $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show run");
    }
    if (!empty($running_conf))
    {
      // trimming first and last lines
      $pos = strpos($running_conf, 'Current configuration');
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, $pos);
      }
      // remove 'ntp clock-period' line
			$running_conf = remove_end_of_line_starting_with($running_conf, '!Time');
			$running_conf = remove_end_of_line_starting_with($running_conf, 'username admin password 5');
      $pos = strrpos($running_conf, "\n");
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, 0, $pos + 1);
      }
    }

    $this->running_conf = $running_conf;
    return $this->running_conf;
  }

  // ------------------------------------------------------------------------------------------------
  /**
	* Generate a configuration cleaner based on the current router configuration
	*/
  function generate_clean(&$configuration)
  {
    // Load router conf if necessary
    if (empty($this->running_conf))
    {
      $this->get_running_conf();
    }

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

  function update_firmware($param = '')
  {
    global $sms_sd_ctx;
    global $status_message;
    global $disk_names;
    $dst_disk = "flash";

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
        foreach ($map_conf as $mkey => $file)
        {
          if (!empty($file))
          {
            $firmware_file = "{$this->fmc_repo}/$file";
            break; // use this first file found
          }
        }
      }
    }
    if (empty($firmware_file))
    {
      sms_log_info(__FUNCTION__ . ": no file specified.\n");
      global $status_message;
      $status_message = "No file specified";
      return SMS_OK;
    }

    clearstatcache(TRUE, $firmware_file);
    if (!file_exists($firmware_file))
    {
      return ERR_NO_FIRMWARE;
    }

    foreach ($disk_names as $disk_name)
    {
    	if (preg_match($disk_name, $firmware_file, $match) > 0)
    	{
    		$dst_disk = $match[0];
    		break;
    	}
    }

    status_progress('Checking installed firmware', 'FIRMWARE');
    // Check if the firmware is currently installed (flash:...)
    $previous_firmware = $this->get_current_firmware_name();
    $previous_firmware_file_name = preg_replace('@^\w+:@', '', $previous_firmware);
    if (basename($firmware_file) == $previous_firmware_file_name)
    {
      $status_message = 'Firmware already installed';
      return SMS_OK;
    }

    status_progress('Checking available space on the router', 'FIRMWARE');
    // Get file size
    $firmware_size = filesize($firmware_file);

    // Check size
    $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show $dst_disk: | inc available | bytes used | total");
    if ($line !== false)
    {
      if (preg_match('/(?<mem>\d+)( bytes)? available/', $line, $matches) > 0)
      {
        $flash_avail = $matches['mem'];
      }
    }
    if (empty($flash_avail) || $flash_avail < $firmware_size)
    {
      // not enough memory on device
      return ERR_SD_ENOMEM;
    }

    status_progress('Transfering firmware file', 'FIRMWARE');
    // Transfer firmware
    $src = $firmware_file;
    $dst = basename($firmware_file);
    $sd_node_ip_addr = $this->sd->SD_NODE_IP_ADDR;

    if($this->sd->SD_CONF_ISIPV6)
    {
    	$sd_node_ip_addr = $_SERVER['SMS_ADDRESS_IPV6'];
    }

    $ret = send_file_to_router($src, $dst, $sd_node_ip_addr);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // Check file
    if (strpos($param, "NO_VERIFY") === false)
    {
      status_progress('Verifying transfered file', 'FIRMWARE');

      $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "verify $dst_disk:$dst", "lire dans sdctx", 150000);
      if (strpos($line, 'uccessful') === false)
      {
        if (strpos($line, 'Verified') === false)
        {
          return ERR_FIRMWARE_CORRUPTED;
        }
      }
    }

    status_progress('Configuring boot file', 'FIRMWARE');

    // Configure new firmware
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#");
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "no boot system", "(config)#");
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "boot system {$dst_disk}:$dst", "(config)#");
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
      cisco_nexus_disconnect();
      sleep(70);
      $ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
      if ($ret != SMS_OK)
      {
        return $ret;
      }
      status_progress('Connecting to the device', 'FIRMWARE');

      $loop = 5;
      while ($loop > 0)
      {
        sleep(10); // wait for ssh to come up
        $ret = cisco_nexus_connect();
        if ($ret == SMS_OK)
        {
          break;
        }
        $loop--;
      }

      if ($ret != SMS_OK)
      {
        return $ret;
      }
      status_progress('Checking the firmware currently used', 'FIRMWARE');

      // Check that the device booted on the new firmware
      $new_firmware = $this->get_current_firmware_name();
      $new_firmware_file_name = preg_replace('@^\w+:\/?@', '', $new_firmware);
      if (basename($firmware_file) !== $new_firmware_file_name)
      {
        return ERR_FIRMWARE_CORRUPTED;
      }

      if (strpos($param, "NO_DELETE") === false)
      {
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

  // ------------------------------------------------------------------------------------------------
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

  // ------------------------------------------------------------------------------------------------
  /**
	*
	*/
  function update_conf($flag = false)
  {
    $ret = $this->build_conf($generated_configuration);

    if (!empty($generated_configuration))
    {
      $ret = cisco_nexus_apply_conf($generated_configuration,$flag);
    }
    return $ret;
  }

  function provisioning($flag = false)
  {
    return $this->update_conf($flag);
  }

  function get_staging_conf()
  {
    $staging_conf = PATTERNIZETEMPLATE("staging_conf.tpl");
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $staging_conf, 'STAGING_CONFIG', 'Configuration');
    return $staging_conf;
  }

  function monitoring_conf(&$generated_configuration)
  {
    if ($this->sd->SD_LOG)
    {

      $generated_configuration .= PATTERNIZETEMPLATE('snmp_conf.tpl');
    }
    if ($this->sd->SD_LOG_MORE)
    {
      $generated_configuration .= PATTERNIZETEMPLATE('syslog_conf.tpl');
    }

    return SMS_OK;
  }

  function get_data_files($event, $src_dir, $file_pattern, $dst_dir)
  {
    global $sms_sd_ctx;
    global $status_message;

    $ret = SMS_OK;
    $repo_dir = $_SERVER['FMC_REPOSITORY'];

    status_progress('Reading files on device', $event);

    $file_list = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir {$src_dir}");
    $patterns = array();
    $patterns[0] = '@\.@';
    $patterns[1] = '@\*@';
    $patterns[2] = '@\?@';
    $replacements = array();
    $replacements[0] = '\.';
    $replacements[1] = '\S*';
    $replacements[2] = '.?';
    $pattern = preg_replace($patterns, $replacements, $file_pattern);
    $pattern = "@^ .* (?<file>{$pattern})\s*$@m";
    echo "PATTERN [$pattern]\n";

    if (preg_match_all($pattern, $file_list, $matches) > 0)
    {
      foreach ($matches['file'] as $file_line)
      {
        status_progress("{$status_message}Transfering file {$src_dir}{$file_line} to {$repo_dir}/{$dst_dir}/{$file_line}", $event);
        try
        {
          scp_from_router("{$src_dir}{$file_line}", "{$repo_dir}/{$dst_dir}/{$file_line}");
          // Check file size
          check_file_size("{$repo_dir}/{$dst_dir}/{$file_line}", $file_line, false, str_replace(':', '', $src_dir));
          $status_message .= "{$src_dir}{$file_line} OK\n | ";
          // create the .meta file
          $tmp = preg_split("@/@", $dst_dir);
          $repo = $tmp[0];
          $gtod = gettimeofday();
          $date_modif = floor($gtod['sec'] * 1000 + $gtod['usec'] / 1000);
          $meta_file = "{$repo_dir}/{$dst_dir}/.meta_{$file_line}";
          $meta_content = <<< EOF
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<metadata>
    <map>
        <entry>
            <key>FILE_TYPE</key>
            <value>binary</value>
        </entry>
        <entry>
            <key>DATE_MODIFICATION</key>
            <value>{$date_modif}</value>
        </entry>
        <entry>
            <key>COMMENT</key>
            <value>Uploaded from {$this->sdid}</value>
        </entry>
        <entry>
            <key>REPOSITORY</key>
            <value>{$repo}</value>
        </entry>
        <entry>
            <key>DATE_CREATION</key>
            <value>{$date_modif}</value>
        </entry>
        <entry>
            <key>CONFIGURATION_FILTER</key>
            <value></value>
        </entry>
        <entry>
            <key>TYPE</key>
            <value>UPLOAD</value>
        </entry>
        <entry>
            <key>TAG</key>
            <value>{$src_dir}{$file_line}</value>
        </entry>
    </map>
</metadata>
EOF;
          file_put_contents($meta_file, $meta_content);
        }
        catch (SmsException $e)
        {
          unlink("{$repo_dir}/{$dst_dir}/{$file_line}");
          $ret = $e->getCode();
          $status_message .= $e->getMessage();
          $status_message .= "\n | ";
          if ($sms_sd_ctx === null)
          {
            // connection lost, try a last time
            $res = cisco_nexus_connect();
            if ($res !== SMS_OK)
            {
              // give up
              $status_message .= "Connection lost with the device, stopping the transfer";
              return $ret;
            }
          }
        }
      }
    }
    return $ret;
  }

  function reboot($event, $params = '')
  {
    status_progress('Reloading device', $event);

    func_reboot($event);
    cisco_nexus_disconnect();
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
      $ret = cisco_nexus_connect();
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

  	$ret = cisco_nexus_connect();

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