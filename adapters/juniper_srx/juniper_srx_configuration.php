<?php
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('juniper_srx', 'common.php');
require_once load_once('juniper_srx', 'adaptor.php');
require_once load_once('juniper_srx', 'juniper_srx_apply_conf.php');
require_once load_once('juniper_srx', 'juniper_srx_apply_restore_conf.php');

require_once "$db_objects";
class juniper_srx_configuration
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
  var $is_ztd;

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
    $net = get_network_profile();
    $this->sd = &$net->SD;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Get running configuration from the router
   */
  function get_running_conf()
  {
    global $sms_sd_ctx;
    $SMS_OUTPUT_BUF = '';

    // Run the CLI Cmd
    $SMS_OUTPUT_BUF .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show config | display set");

    $config_string = "";

    if ($SMS_OUTPUT_BUF != '')
    {
      $line = get_one_line($SMS_OUTPUT_BUF);
      $i = 0;
      while ($line !== false)
      {
        if ($i > 0)
        {
          if (strpos($line, $sms_sd_ctx->getPrompt()) === false)
          {
            $config_string .= $line . "\n";
          }
        }
        $line = get_one_line($SMS_OUTPUT_BUF);
        $i++;
      }
    }

    $this->running_conf = $config_string;
    return $this->running_conf;
  }

  // ------------------------------------------------------------------------------------------------
  function get_staging_conf()
  {
    $staging_conf = PATTERNIZETEMPLATE("staging_conf.tpl");
    return $staging_conf;
  }

  // ------------------------------------------------------------------------------------------------
  function update_firmware($param = '')
  {
    global $sms_sd_ctx;
    global $status_message;
    global $SD;

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

    status_progress('Checking installed firmware', 'FIRMWARE');
    // Check if the firmware is currently installed (flash:...)
    // @TODO


    // Checking available space on the router
    // @TODO
    //status_progress('Checking available space on the router', 'FIRMWARE');


    status_progress('Transfering firmware file', 'FIRMWARE');
    // Transfer firmware
    $src = $firmware_file;
    $dst = '/var/tmp/' . rand(5, 15) . '_' . basename($firmware_file);

    $ipaddr = $sms_sd_ctx->getIpAddress();
    $login = $sms_sd_ctx->getLogin();

    try
    {
      $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l $login -a $ipaddr -p $SD->SD_PASSWD_ENTRY", $output);
    }
    catch (Exception | Error $e)
    {
      return $e->getMessage();
    }

    // Check if the file is transfered
    status_progress('Verifying transfered file', 'FIRMWARE');

    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "file list $dst", 3000000);

    $tab[0] = 'No such file or directory';
    $tab[1] = $dst;
    $tab[2] = $sms_sd_ctx->getPrompt();

    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

    if ($index != 1)
    {
      return ERR_FIRMWARE_CORRUPTED;
    }

    // Update the firmware
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "request system software add $dst", $sms_sd_ctx->getPrompt());
    unset($tab);
    $tab[0] = 'ERROR: It may have been corrupted during download.';
    $tab[1] = 'Current configuration not compatible with';
    $tab[2] = $sms_sd_ctx->getPrompt();
    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab, 100000);

    if ($index == 2)
    {
      // request system reboot
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "request system reboot");
      unset($tab);
      $tab[0] = 'Reboot the system ? [yes,no] (no)';
      $tab[1] = $sms_sd_ctx->getPrompt();
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

      if ($index == 0)
      {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "yes");
        $ret = $this->reboot('FIRMWARE');
        if ($ret != SMS_OK)
        {
          return ERR_SD_CMDFAILED;
        }
      }
      else
      {
        return ERR_SD_CMDFAILED;
      }
    }
    else
    {
      return ERR_FIRMWARE_CORRUPTED;
    }

    return SMS_OK;
  }
  function get_generated_conf($revision_id = NULL)
  {
    if (!isset($revision_id))
    {
      return "";
    }
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
    return $res;
  }

  //------------------------------------------------------------------------------------------------
  function restore_conf($configuration)
  {
    $ret = juniper_srx_apply_restore_conf($configuration);
    return $ret;
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
    $configuration .= '';
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

  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
  function update_conf()
  {
    $ret = $this->build_conf($generated_configuration);

    if (!empty($generated_configuration))
    {
      $ret = juniper_srx_apply_conf($generated_configuration);
    }

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
  function provisioning()
  {
    return $this->update_conf();
  }

  // ------------------------------------------------------------------------------------------------
  function reboot($event, $params = '')
  {
    status_progress('Reloading device', $event);
    func_reboot();
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
      $ret = juniper_srx_connect();
      if ($ret == SMS_OK)
      {
        break;
      }
      $loop--;
    }

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Mise a jour de la licence
   * Attente du reboot de l'equipement
   */
  function update_license()
  {

    // Globals
    global $sms_sd_ctx;
    global $status_message;
    global $sendexpect_result;

    // Get Licenced
    $ret = get_repo_files_map($map_conf, $error, 'License');
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
          $licence_file = "{$this->fmc_repo}/$file";
          break; // use this first file found
        }
      }
    }

    if (empty($licence_file))
    {
      sms_log_info(__FUNCTION__ . ": no file specified.\n");
      $status_message = "No file specified";
      return ERR_SD_BAD_FILE_URI;
    }

    if (!file_exists($licence_file))
    {
      $status_message = "No file specified";
      return ERR_SD_BAD_FILE_URI;
    }

    $src = $licence_file;
    $dst = "/config/jun-license.xml";
    $ipaddr = $sms_sd_ctx->getIpAddress();
    $login = $sms_sd_ctx->getLogin();
    $passwd = $sms_sd_ctx->getPassword();

    try
    {
      $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l $login -a $ipaddr -p $passwd", $output);
    }
    catch (Exception | Error $e)
    {
      return $e->getMessage();
    }

    // WORKING
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "request system license add /config/jun-license.xml");

    $tab[0] = 'add license complete (no errors)';
    $tab[1] = $sms_sd_ctx->getPrompt();

    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

    if ($index == 0)
    {
      return SMS_OK;
    }
    else
    {
      return ERR_SD_PARSING_FAILED;
    }
  }
}

?>