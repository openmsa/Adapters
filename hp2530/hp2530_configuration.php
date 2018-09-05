<?php
/*
 * Version: $Id: device_configuration.php 58927 2012-06-11 15:15:18Z abr $
* Created: Feb 12, 2009
*/
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
// NSP Bugfix 2017.07.28 MOD
// Modified Device Adaptor Name
require_once load_once('hp2530', 'hp2530_apply_conf.php');
require_once "$db_objects";

// NSP Bugfix 2017.07.28 MOD
class hp2530_configuration
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
  // NSP Bugfix 2017.07.28 MOD START
  // Modified Device Adaptor Name
  function __construct($sdid, $is_provisionning = false)
  // NSP Bugfix 2017.07.28 MOD END
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
    // NSP Bugfix 2017.07.28 MOD START
    // Modified Device Adaptor Name
    $ret = hp2530_apply_conf($generated_configuration);
    // NSP Bugfix 2017.07.28 MOD END
    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get running configuration from the router
  */
  function get_running_conf()
  {
    global $sms_sd_ctx;
//[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
    $running_conf = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show run", $sms_sd_ctx->getPrompt());
//[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
    // NSP Bugfix 2017.07.29 MOD START
	$running_conf = explode("\n", $running_conf);

    //remove first 3 lines
	array_shift($running_conf);
	array_shift($running_conf);
	array_shift($running_conf);

	//remove last line
	array_pop($running_conf);
	$running_conf_final = implode("\n", $running_conf);
    $this->running_conf = $running_conf_final;
    // NSP Bugfix 2017.07.29 MOD END

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
