<?php
/*
 * Version: $Id: apic_configuration.php 58927 2012-06-11 15:15:18Z abr $
* Created: Feb 12, 2009
*/
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once 'smsd/expect.php';

require_once load_once('cisco_apic', 'common.php');
require_once load_once('cisco_apic', 'adaptor.php');
require_once load_once('cisco_apic', 'apic_apply_conf.php');
require_once "$db_objects";


class apic_configuration
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
    $net = get_network_profile();
    $this->sd=&$net->SD;

  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get running configuration from the router via apic
  *
  */

function get_running_conf()
	{
	  // Get the URLs from apicConnection class
	  $connect = new ApicConnection();
	  $url_policy = $connect->get_url_policy();

	  $ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL, $url_policy);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	  curl_setopt($ch, CURLOPT_VERBOSE, 1);
	  $responses = curl_exec ($ch);

	  $running_conf = get_config_line($responses);
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

    if(!empty($generated_configuration))
    {
       $ret = apic_apply_conf($generated_configuration,$this->is_ztd);
    }
    return $ret;
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

  function monitoring_conf(&$generated_configuration)
  {
  	if($this->sd->SD_LOG)
  	{

  		$generated_configuration .= PATTERNIZETEMPLATE('snmp_conf.tpl');

  	}
  	if($this->sd->SD_LOG_MORE)
  	{
  		$generated_configuration .= PATTERNIZETEMPLATE('syslog_conf.tpl');
  	}

  	return SMS_OK;
  }
}

?>