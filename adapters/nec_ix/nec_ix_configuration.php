<?php
/*
 * Version: $Id: device_configuration.php 58927 2012-06-11 15:15:18Z abr $
*  Created: Dec 06, 2018
*/
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once load_once('nec_ix', 'nec_ix_apply_conf.php');
require_once "$db_objects";

class nec_ix_configuration
{
  var $conf_path;           // Path for previous stored configuration files
  var $sdid;                // ID of the SD to update
  var $running_conf;        // Current configuration of the router
  var $profile_list;        // List of managed profiles
  var $previous_conf_list;  // Previous generated configuration loaded from files
  var $conf_list;           // Current generated configuration waiting to be saved
  var $addon_list;          // List of managed addon cards
  var $fmc_repo;            // repository path without trailing /
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
  }

  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
  function update_conf()
  {
    $this->build_conf($generated_configuration);
    $ret = nec_ix_apply_conf($generated_configuration);
    return $ret;
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
      sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf", '(config)#');
      $running_conf = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show run", '(config)#');
    }
    if (!empty($running_conf))
    {
      // trimming first and last lines
      $pos = strpos($running_conf, 'Current configuration');
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, $pos);
      }

      $running_conf = remove_line_starting_with($running_conf, 'Current configuration');
      $running_conf = remove_line_starting_with($running_conf, '(config)#');
      $pos = strrpos($running_conf, "\n");
      if ($pos !== false)
      {
        $running_conf = substr($running_conf, 0, $pos + 1);
      }
    }
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", $sms_sd_ctx->getPrompt());

    $this->running_conf = $running_conf;
    return $this->running_conf;
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
