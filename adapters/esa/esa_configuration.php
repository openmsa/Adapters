<?php

/*
 * Version: $Id: esa_configuration.php 37371 2010-11-30 17:46:40Z tmt $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once load_once('esa', 'esa_apply_conf.php');

/** Main configuration manager
 * All the profile managed are listed in the constructor.
 */
class esa_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $net_conf;
  var $sd;
  var $fmc_repo;

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
    $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
  }

  /**
   * Generate the begining of the configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_begin_conf(&$configuration)
  {
    return get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_CONFIG', 'Configuration');
  }

  /**
   * Generate the end of the configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_end_conf(&$configuration)
  {
    return get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
  }

  /**
   * Generate a full configuration
   * Uses the running conf if specified or previous conf if present to perform deltas
   * @param $configuration   configuration buffer to fill
   */
  function generate(&$configuration)
  {
    return SMS_OK;
  }

  /**
   * Save current configuration to file
   */
  function save_generated()
  {
    // TODO : sauvegarder les pre et les post ?
    return SMS_OK;
  }

  /**
   * Load a previous generated configuration
   */
  function load_previous_generated()
  {
    return SMS_OK;
  }

  /**
   * Get running configuration from the router
   */
  function get_running_conf()
  {
    global $sms_sd_ctx;

                $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "saveconfig");

                unset($tab);
                $tab[0] = '[Y]>';
                $tab[1] = '[1]>';
                $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

                if($index === 0)
                {
      $buffer = $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "n");
                }
                if($index === 1)
                {
                        $buffer = $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "2");
                }

    $confRunFile = explode(' ', $buffer);
    $confFileTmp = $confRunFile[7];
    $confFile = $this->get_string_between($confFileTmp, '"', '"');

    $xmlFile = explode('/', $confFile);

    $src = $confFile;
    $dst = "/tmp/" . $xmlFile[2];

    $ipaddr = $sms_sd_ctx->getIpAddress();
    $login = $sms_sd_ctx->getLogin();
    $passwd = $sms_sd_ctx->getPassword();

    $network = get_network_profile();
    $SD = &$network->SD;
    $sd_mgt_port = $SD->SD_MANAGEMENT_PORT;

    try
    {
      $ret = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l $login -a $ipaddr -p $passwd -P $sd_mgt_port -r", $output);
      if ($ret != SMS_OK)
      {
        return '';
      }
    }
    catch (Exception | Error $e)
    {
      return $e->getMessage();
    }

    // Delete any date issue for Change Management and any comments <!-- **** -->
    // Delete all between <users></users> for any Password
    $fileContent = file_get_contents("/tmp/" . $xmlFile[2]);
    $stringToDisplayTmp = preg_replace('/<!--[^>]*-->/', '', $fileContent);
    $stringToDisplay = preg_replace('#('.preg_quote("<users>").')(.*)('.preg_quote("</users>").')#si', '', $stringToDisplayTmp);

    return trim($stringToDisplay);
  }

  /**
   * Store the running configuration for further deltas
   */
  function store_running($running_conf)
  {
    $this->running_conf = $running_conf;
    return SMS_OK;
  }
  function build_conf(&$generated_configuration)
  {
    $ret = $this->generate_begin_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = $this->generate($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $ret = $this->generate_end_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }
  function restore_conf($configuration)
  {
    $ret = esa_apply_conf($configuration);
    return $ret;
  }
  function update_conf($copy_to_startup = false)
  {
    $ret = $this->build_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = esa_apply_conf($generated_configuration, $copy_to_startup);
    if ($ret === SMS_OK)
    {
      $this->save_generated();
    }

    return $ret;
  }
  function provisioning()
  {
    return $this->update_conf(true);
  }

  /**
   * Get status
   */
  function get_status()
  {
  }

  /**
   * Update firmware of the device
   */
  function update_firmware()
  {
    global $sms_sd_ctx;
    global $apply_errors;
    global $sendexpect_result;

    status_progress('Checking for upgrades', 'FIRMWARE');
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "upgrade", "upgrade");

    $state = 0;
    $tab[0] = '[Y]>';
    $tab[1] = '[N]>';
    $tab[2] = '[y]>';
    $tab[3] = '[n]>';
    $tab[4] = 'Failed to connect to manifest server.';
    $tab[5] = 'No Available upgrades.';
    $tab[6] = 'No available upgrades.';
    $tab[7] = 'Failed to authenticate with manifest server';
    $tab[8] = 'Failure downloading upgrade list: DNS lookup failed.';
    $tab[9] = 'Upgrades available.';
    $tab[10] = '[]>';
    $tab[11] = $sms_sd_ctx->getPrompt();

    while ($state == 0)
    {
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab, 100000);
      switch ($index)
      {
        case 0:
        case 1:
        case 2:
        case 3:
          $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
          break;
        case 4:
          status_progress('Failure downloading upgrade list: Failed to connect to manifest server.', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Failed to connect to manifest server.\n");
          return ERR_SD_FIRMWARE_NO_MANIFEST_SERVER;
          break;
        case 5:
        case 6:
          return SMS_OK;
          break;
        case 7:
          status_progress('Error occured', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Error occured\n");
          return ERR_SD_AUTH_MANIFEST_SERVER;
          break;

        case 8:
          status_progress('Failure downloading upgrade list: DNS lookup failed', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Failure downloading upgrade list: DNS lookup failed\n");
          return ERR_SD_DNS_ERROR;
          break;

        case 9:
          $state = 1;
          break;
        /* -- C000V Version -- */
        case 10:
          $state = 2;
          break;
        /* -- END CASE -- */
        case 11:
          status_progress('Error occured', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Error occured\n");
          return ERR_SD_CMDFAILED;
          break;
      }
    }

    /* -- START C000V Version -- */
    if ($state == 2)
    {
      //DOWNLOADINSTALL
      //$sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "DOWNLOADINSTALL");
      $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "DOWNLOADINSTALL", "DOWNLOADINSTALL");

      $state = 0;
      $tab[0] = '[Y]>';
      $tab[1] = '[N]>';
      $tab[2] = '[y]>';
      $tab[3] = '[n]>';
      $tab[4] = 'Failed to connect to manifest server.';
      $tab[5] = 'No Available upgrades.';
      $tab[6] = 'No available upgrades.';
      $tab[7] = 'Failed to authenticate with manifest server';
      $tab[8] = 'Failure downloading upgrade list: DNS lookup failed.';
      $tab[9] = 'Upgrades available.';
      $tab[10] = '[]>';
      $tab[11] = $sms_sd_ctx->getPrompt();

      while ($state == 0)
      {
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab, 100000);
        switch ($index)
        {
          case 0:
          case 1:
          case 2:
          case 3:
            $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
            break;
          case 4:
            status_progress('Failure downloading upgrade list: Failed to connect to manifest server.', 'FIRMWARE');
            sms_log_error(__FILE__ . ':' . __LINE__ . ": Failed to connect to manifest server.\n");
            return ERR_SD_FIRMWARE_NO_MANIFEST_SERVER;
            break;
          case 5:
          case 6:
            return SMS_OK;
            break;
          case 7:
            status_progress('Error occured', 'FIRMWARE');
            sms_log_error(__FILE__ . ':' . __LINE__ . ": Error occured\n");
            return ERR_SD_AUTH_MANIFEST_SERVER;
            break;
          case 8:
            status_progress('Failure downloading upgrade list: DNS lookup failed', 'FIRMWARE');
            sms_log_error(__FILE__ . ':' . __LINE__ . ": Failure downloading upgrade list: DNS lookup failed\n");
            return ERR_SD_DNS_ERROR;
            break;

          case 9:
            $state = 1;
            break;
          /* -- C000V Version -- */
          case 10:
            $state = 2;
            break;
          /* -- END CASE -- */
          case 11:
            status_progress('Error occured', 'FIRMWARE');
            sms_log_error(__FILE__ . ':' . __LINE__ . ": Error occured\n");
            return ERR_SD_CMDFAILED;
            break;
        }
      }

      if (strpos($sendexpect_result, ']>') === false)
      {
        unset($tab);
        $tab[0] = ']>';
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      }

      $bufData = explode("\n", $sendexpect_result);
      $firmList = array();

      foreach ($bufData as $data)
      {
        if (preg_match('@. AsyncOS@', $data))
        {
          array_push($firmList, $data);
        }
      }
      $recentFirm = count($firmList);

      unset($tab);
      $tab[0] = '[Y]>';
      $tab[1] = $sms_sd_ctx->getPrompt();
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $recentFirm);
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

      if ($index == 0)
      {
        unset($tab);
        $tab[0] = '[N]>';
        $tab[1] = $sms_sd_ctx->getPrompt();
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
        if ($index == 0)
        {
          unset($tab);
          $tab[0] = '[Y]>';
          $tab[1] = $sms_sd_ctx->getPrompt();
          $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
          $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

          if ($index == 0)
          {
            unset($tab);
            $tab[0] = '[Y]>';
            $tab[1] = $sms_sd_ctx->getPrompt();
            $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
            $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

            if ($index == 0)
            {
              // Change the status for the GUI
              status_progress('Firmware upgrade in progress...', 'FIRMWARE');

              $tab[0] = '[30]>';
              $tab[1] = $sms_sd_ctx->getPrompt();

              $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
              $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab, 100000);

              if ($index == 0)
              {
                $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
                // Update Firmware Upgrade Successful
                status_progress("Wait for the asset update", 'FIRMWARE');
                return SMS_OK;
              }
              else
              {
                // Error occur
                status_progress('Upgrade failure', 'FIRMWARE');
                sms_log_error(__FILE__ . ':' . __LINE__ . ": Upgrade failure\n");
                return ERR_SD_CMDFAILED;
              }
            }
          }
        }
      }

      /* -- OLD VERSION -- */
    }
    else
    {
      if (strpos($sendexpect_result, ']>') === false)
      {
        unset($tab);
        $tab[0] = ']>';
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      }

      $bufData = explode("\n", $sendexpect_result);
      $firmList = array();

      foreach ($bufData as $data)
      {
        if (preg_match('@. AsyncOS@', $data))
        {
          array_push($firmList, $data);
        }
      }
      $recentFirm = count($firmList);

      unset($tab);
      $tab[0] = 'upgrade? [Y]>';
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $recentFirm);
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

      if ($index == 0)
      {
        // Change the status for the GUI
        status_progress('Firmware upgrade in progress...', 'FIRMWARE');

        $tab[0] = '[30]>';
        $tab[1] = $sms_sd_ctx->getPrompt();

        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

        if ($index == 0)
        {
          // Update Firmware Upgrade Successful
          status_progress("Wait for the asset update", 'FIRMWARE');
          return SMS_OK;
        }
        else
        {
          // Error occur
          status_progress('Upgrade failure', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Upgrade failure\n");
          return ERR_SD_CMDFAILED;
        }
      }
    }

    foreach ($apply_errors as $apply_error)
    {
      if (preg_match($apply_error, $sendexpect_result) > 0)
      {
        //sms_log_error(__FILE__.':'.__LINE__.": [[!!! $sendexpect_result !!!]]\n");
        status_progress($apply_error, 'FIRMWARE');
        if ($apply_error == '@No available upgrades@')
        {
          status_progress('No Available upgrades', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": No Available upgrades\n");
          return ERR_SD_FIRMWARE_NO_UPGRADES;
        }
        if ($apply_error == '@Failure downloading upgrade list@')
        {
          status_progress('Failure downloading upgrade list', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Failure downloading upgrade list\n");
          return ERR_SD_FIRMWARE_NO_MANIFEST_SERVER;
        }
        if ($apply_error == '@Failure@')
        {
          status_progress('Failure downloading upgrade list: Failed to connect to manifest server.', 'FIRMWARE');
          sms_log_error(__FILE__ . ':' . __LINE__ . ": Failure downloading upgrade list: Failed to connect to manifest server.\n");
          return ERR_SD_FIRMWARE_NO_MANIFEST_SERVER;
        }

        return ERR_SD_CMDFAILED;
      }
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
  protected function get_string_between($string, $start, $end)
  {
    $string = " " . $string;
    $ini = strpos($string, $start);
    if ($ini == 0)
      return "";
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }

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
    $dst = "/configuration/license.xml";
    $ipaddr = $sms_sd_ctx->getIpAddress();
    $login = $sms_sd_ctx->getLogin();
    $passwd = $sms_sd_ctx->getPassword();

    try
    {
      $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d /$dst -l $login -a $ipaddr -p $passwd", $output);
    }
    catch (Exception | Error $e)
    {
      return $e->getMessage();
    }

    // WORKING
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "loadlicense license.xml");
    $tab[0] = '[N]';
    $tab[1] = 'loadlicense license.xml';
    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
     if ($index == 0)
    {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "Y");
    }

#    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "loadlicense license.xml", "loadlicense license.xml");

    $tab[0] = '[Y]>';
    $tab[1] = '-Press Any Key For More-';
    $tab[2] = $sms_sd_ctx->getPrompt();
    $tab[3] = 'Unknown command or missing feature key: loadlicense';
    $tab[4] = '[N]';

    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

    /* -- License Expired -- */
    $error_array = array(
        '@License has expired.@'
    );
    foreach ($error_array as $apply_error)
    {
      if (preg_match($apply_error, $sendexpect_result) > 0)
      {
        if ($apply_error == '@License has expired.@')
        {
          return ERR_SD_LICENSE_EXPIRED;
        }
      }
    }

    if ($index == 2)
    {
      return SMS_OK;
    }

    if ($index == 3)
    {
      return ERR_SD_PARSING_FAILED;
    }

     if ($index == 4)
    {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "Y");
    }


    // If there is a License Agreement
    unset($tab);
    $tab[0] = '-Press Any Key For More-';
    $tab[1] = 'Parsing failed.  Aborting.';
    $tab[2] = $sms_sd_ctx->getPrompt();
    $tab[3] = 'This license file is not valid for this platform';
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "Y");
    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

    // File Corrupted or Invalid
    if ($index == 1 || $index == 3)
    {
      return ERR_SD_PARSING_FAILED;
    }

    if ($index == 0)
    {
      do
      {
        unset($tab);
        $tab[0] = '-Press Any Key For More-';
        $tab[1] = 'Do you accept the above license agreement? []>';
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      } while ($index == 0);

      // Out of the loop
      if ($index == 1)
      {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "yes");
        $tab[0] = '[1]>';
        $tab[1] = $sms_sd_ctx->getPrompt();
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
        if ($index == 1)
        {
          return SMS_OK;
        }
        else
        {
          return ERR_SD_PARSING_FAILED;
        }
      }
    }
    else
    {
      // Not a License Agreement to accept
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "");
      $tab[1] = $sms_sd_ctx->getPrompt();
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      if ($index == 1)
      {
        return SMS_OK;
      }
      else
      {
        /* -- License Expired -- */
        $error_array = array(
            '@License has expired.@'
        );
        foreach ($error_array as $apply_error)
        {
          if (preg_match($apply_error, $sendexpect_result) > 0)
          {
            if ($apply_error == '@License has expired.@')
            {
              return ERR_SD_LICENSE_EXPIRED;
            }
          }
        }
        return ERR_SD_PARSING_FAILED;
      }
    }
  }
}

/**
 * @}
 */
?>
