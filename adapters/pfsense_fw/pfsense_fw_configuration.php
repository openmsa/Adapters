<?php

/*
 * Version: $Id: pfsense_fw_configuration.php 37371 2010-11-30 17:46:40Z tmt $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once load_once('pfsense_fw', 'pfsense_fw_apply_conf.php');

/** Main configuration manager
 * All the profile managed are listed in the constructor.
 */
class pfsense_fw_configuration
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
echo "In get running function\n";
	$prompt = $sms_sd_ctx->getPrompt();
	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'cat /cf/conf/config.xml', $prompt);
	return $buffer;
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
    $ret = pfsense_fw_apply_conf($configuration);
    return $ret;
  }
  function update_conf($copy_to_startup = false)
  {
    $ret = $this->build_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = pfsense_fw_apply_conf($generated_configuration, $copy_to_startup);
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
 /* function get_status()
  {
  }*/

  /**
   * Update firmware of the device
   */
/*  function update_firmware()
  {

  }
*/
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
 /* function update_license()
  {
  }*/
}
/**
 * @}
 */
?>
