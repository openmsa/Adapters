<?php
/*
 * Version: $Id: fujitsu_ipcom_configuration.php 58927 2012-06-11 15:15:18Z abr $
* Created: Feb 12, 2009
*/
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('fujitsu_ipcom', 'common.php');
require_once load_once('fujitsu_ipcom', 'adaptor.php');
require_once load_once('fujitsu_ipcom', 'fujitsu_ipcom_apply_conf.php');
require_once "$db_objects";

class fujitsu_ipcom_configuration
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
      $running_conf = remove_end_of_line_starting_with($running_conf, 'Current configuration');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'ntp clock-period');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'enable secret 5');
      $running_conf = remove_end_of_line_starting_with($running_conf, ' create profile sync');
      $running_conf = remove_end_of_line_starting_with($running_conf, 'username fujitsu_ipcom password 7');
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

    if (!empty($generated_configuration))
    {
      $ret = fujitsu_ipcom_apply_conf($generated_configuration);
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

  function reboot($event, $params = '')
  {
    status_progress('Reloading device', $event);

    func_reboot($event);
    fujitsu_ipcom_disconnect();
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
      $ret = fujitsu_ipcom_connect();
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

  	$ret = fujitsu_ipcom_connect();

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
