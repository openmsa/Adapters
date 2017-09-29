<?php
/*
 * Version: $Id: device_configuration.php 58927 2012-06-11 15:15:18Z abr $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('cisco_asa_generic', 'device_apply_conf.php');
require_once "$db_objects";
class device_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $profile_list; // List of managed profiles
  var $previous_conf_list; // Previous generated configuration loaded from files
  var $conf_list; // Current generated configuration waiting to be saved
  var $addon_list; // List of managed addon cards
  var $fmc_repo; // repository path without trailing /

  // ------------------------------------------------------------------------------------------------
  /**
   * Constructor
   */
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->conf_pflid = 0;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
  }

  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
  function update_conf()
  {
    $this->build_conf($generated_configuration);
    $ret = device_apply_conf($generated_configuration);

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Get running configuration from the router
   */
  function get_running_conf()
  {
    global $sms_sd_ctx;

    // GEstion multi context
    $net_profile = get_network_profile();
    $SD = &$net_profile->SD;
    $context = $SD->SD_CONFIGVAR_list['ASA_CONTEXT']->VAR_VALUE;
    echo "\n Context:" . $context . "\n";
    if ($SD->SD_CONFIGVAR_list['ASA_CONTEXT']->VAR_VALUE !== '')
    {
      sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "changeto context " . $context);
    }

    $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show run | exc Last configuration change");
    if (!empty($running_conf))
    {
      // trimming first line
      $running_conf = str_replace("show run | exc Last configuration change", "", $running_conf);

      // remove 'ntp clock-period' line
      $running_conf = remove_end_of_line_starting_with($running_conf, 'ntp clock-period');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'enable secret 5');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create profile sync');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'username device password 7');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create cnf-files version-stamp');
      $pos = strrpos($running_conf, "\n");
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, 0, $pos + 1);
      }
    }

    $this->running_conf = $running_conf;
    return $this->running_conf;
  }

  function get_current_firmware_name()
  {
    global $sms_sd_ctx;

    // Grab current firmware file name
    $line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show ver | inc image file");
    $current_firmware = substr($line, strpos($line, '"') + 1, strrpos($line, '"') - strpos($line, '"') - 1);

    return $current_firmware;
  }

  function update_firmware()
  {
    global $sms_sd_ctx;

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
          $firmware_file = basename($file);
          $firm_repo_file = "{$this->fmc_repo}/$file";
          break; // use this first file found
        }
      }
    }
    if (empty($firm_repo_file))
    {
      // no firmware attached
      return ERR_NO_FIRMWARE;
    }

    status_progress('Reading device current firmware', 'FIRMWARE');

    $previous_firmware = $this->get_current_firmware_name();
    if (strpos($previous_firmware, $firmware_file) !== false)
    {
      echo "$firm_repo_file is already present as $previous_firmware\n";
      return SMS_OK;
    }

    status_progress('Checking firmware file', 'FIRMWARE');

    // Get file size
    $firmware_size = filesize($firm_repo_file);

    // Check size
    $line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show flash: | inc available | bytes used | total");
    if ($line !== false)
    {
      if (preg_match('/(?<mem>\d+)( bytes)? available/', $line, $matches) > 0)
      {
        $flash_avail = $matches['mem'];
      }
      else if (preg_match('/(?<mem>\d+)( bytes)? free/', $line, $matches) > 0)
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
    $src = $firm_repo_file;
    $dst = $firmware_file;
    $ret = scp_to_router($src, $dst);
    if ($ret === ERR_SD_SCP)
    {
      // Try tftp
      sms_log_info(__FILE__.':'.__LINE__.": scp failed trying tftp for $src\n");

      $tftp_dir = "{$_SERVER['TFTP_BASE']}/{$this->sdid}";
      if (!is_dir($tftp_dir))
      {
        if (mkdir($tftp_dir, 0775) === false)
        {
          return ERR_LOCAL_FILE;
        }
      }
      $tftp_file = "$tftp_dir/$dst";
      copy($src, $tftp_file);
      $sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];
      sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/{$this->sdid}/$dst flash:$dst", "]?");
      unset ($tab);
      $tab[0] = "]?";
      $tab[1] = $sms_sd_ctx->getPrompt();
      do
      {
        $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "", $tab, 14400000);
      }
      while ($index !== 1);

      unlink($tftp_file);

      if (strpos($buffer, 'Error') !== false)
      {
        sms_log_error(__FILE__.':'.__LINE__.": tftp failed $buffer\n");
        return ERR_SD_TFTP;
      }
      $ret = SMS_OK;
    }

    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    status_progress('Verifying firmware file', 'FIRMWARE');

    // Check file
    $line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "verify flash:$dst");
    if (strpos($line, 'Signature Verified') === false)
    {
      return ERR_FIRMWARE_CORRUPTED;
    }

    // Remove reference to old firmware
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'show run | inc boot system');
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf t", "(config)#");

    $line = get_one_line($buffer);
    while ($line !== false)
    {
      if (strpos($line, 'boot system') !== false)
      {
        sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "no $line", "(config)#");
      }
      $line = get_one_line($buffer);
    }

    // Configure new firmware
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "boot system disk0:/$dst", "(config)#");
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit");

    $ret = func_write();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    status_progress('Rebooting the device', 'FIRMWARE');
    // reboot
    func_reboot('update firmware');

    device_disconnect(true);

    sleep(70);

    $net_profile = get_network_profile();
    $SD = &$net_profile->SD;
    $ret = wait_for_device_up($SD->SD_IP_CONFIG);
    if ($ret != SMS_OK)
    {
      return $ret;
    }
    status_progress('Connecting to the device', 'FIRMWARE');

    $loop = 5;
    while ($loop > 0)
    {
      sleep(10); // wait for ssh to come up
      $ret = device_connect();
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
    if (strpos($new_firmware, $firmware_file) === false)
    {
      return ERR_FIRMWARE_CORRUPTED;
    }
    status_progress('Removing previous firmware file', 'FIRMWARE');

    // Remove previous firmware file
    unset($tab);
    $tab[0] = '#';
    $tab[1] = ']?';
    $tab[2] = '[confirm]';
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "delete $previous_firmware", $tab);
    while ($index > 0)
    {
      $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "", $tab);
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
    $configuration .= "!PRE CONFIG\n";
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
    $configuration .= "! CONFIGURATION GOES HERE\n";
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Generate the general post-configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_post_conf(&$configuration)
  {
    $configuration .= "!POST CONFIG\n";
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
  function provisioning()
  {
    return $this->update_conf();
  }
  function get_staging_conf()
  {
    $staging_conf = PATTERNIZETEMPLATE("staging_conf.tpl");
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $staging_conf, 'STAGING_CONFIG', 'Configuration');
    return $staging_conf;
  }
}

?>
