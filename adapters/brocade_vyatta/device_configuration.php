<?php
/*
 * Version: $Id: device_configuration.php 58927 2012-06-11 15:15:18Z abr $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('brocade_vyatta', 'device_apply_conf.php');
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
  var $sd;

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

    $profile = get_network_profile();
    $this->sd = $profile->SD;
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

    $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "cli-shell-api showConfig --show-active-only --show-ignore-edit --show-show-defaults ; /opt/vyatta/sbin/vyatta_current_conf_ver.pl");
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

  // ------------------------------------------------------------------------------------------------
  /**
   * Generate the general pre-configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_pre_conf(&$configuration)
  {
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
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Generate the general post-configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_post_conf(&$configuration)
  {
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
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $staging_conf, 'STAGING_CONFIG', 'Configuration');
    return $staging_conf;
  }
  function generate_from_old_revision($revision_id)
  {
    echo ("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
    echo ($get_saved_conf_cmd . "\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK)
    {
      echo ("no running conf found\n");
      return $ret;
    }

    $res = array_to_string($output);

    // replace hidden credentials in SVN... otherwise we losse connection
    $patterns = array();

    $patterns[] = "/OK\s/";

    $replacements = array();
    $replacements[] = "";

    $this->runningconf_to_restore = preg_replace($patterns, $replacements, $res);

    return SMS_OK;
  }
  function restore_conf()
  {
    global $apply_errors;

    global $sms_sd_ctx;
    $ret = SMS_OK;

    echo "SCP mode configuration\n";

    // Request flash space on router
    $file_name = "{$this->sdid}.cfg";
    $full_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;

    $ret = save_file($this->runningconf_to_restore, $full_name);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = save_result_file($this->runningconf_to_restore, 'conf.applied');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    try
    {
      $login = $this->sd->SD_LOGIN_ENTRY;
      $passwd = $this->sd->SD_PASSWD_ENTRY;
      $ip_addr = $this->sd->SD_IP_CONFIG;
      $cmd = "/opt/sms/bin/sms_scp_transfer -s $full_name -d /tmp/$file_name  -l '$login' -a $ip_addr -p '$passwd'";
      exec_local(__FILE__ . ':' . __LINE__, $cmd, $output_array);

      if ($ret === SMS_OK)
      {
        // SCP OK
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'configure', '#');
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "load /tmp/$file_name", '#');
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'commit', '#');
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'save', '#');
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit');
      }
      else
      {
        // SCP ERROR
        sms_log_error(__FILE__ . ':' . __LINE__ . ":SCP Error $ret\n");
      }
    }
    catch (Exception | Error $e)
    {
      if (strpos($e->getMessage(), 'connection failed') !== false)
      {
        return ERR_SD_CONNREFUSED;
      }
      sms_log_error(__FILE__ . ':' . __LINE__ . ":SCP Error $ret\n");
    }

    return $ret;
  }
  function wait_until_device_is_up()
  {
    return wait_for_device_up($this->sd->SD_IP_CONFIG, 60, 30);
  }
}

?>
