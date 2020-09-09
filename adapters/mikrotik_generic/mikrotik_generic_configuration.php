<?php

require_once 'smsd/sms_common.php';
//require_once 'smsd/pattern.php';

require_once load_once('mikrotik_generic', 'common.php');
require_once load_once('mikrotik_generic', 'adaptor.php');
require_once load_once('mikrotik_generic', 'mikrotik_generic_apply_conf.php');
require_once load_once('mikrotik_generic', 'mikrotik_generic_connect.php');

require_once "$db_objects";


class mikrotik_generic_configuration
{
	var $conf_path;           // Path for previous stored configuration files
	var $sdid;                // ID of the SD to update
	var $running_conf;        // Current configuration of the router
	var $conf_to_restore;     // configuration to restore
	var $profile_list;        // List of managed profiles
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
                global $sendexpect_result;

                $cmd = 'export';
		$running_conf = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd);
/*
                if (strpos($running_conf, $cmd) === false)
                {
                  unset($tab);
                  $tab[0] = $sms_sd_ctx->getPrompt();
                  $index = $sms_sd_ctx->expect(__FILE__.':'.__LINE__, $tab, 10000);
                  if ($index == 0)
                  {
                    $running_conf = $sendexpect_result;
                  }
                }
*/
		if (!empty($running_conf))
		{
		  $running_conf = remove_line_starting_with($running_conf, $cmd);
                  $pos = strrpos($running_conf, $sms_sd_ctx->getPrompt());
                  $running_conf = substr($running_conf, 0, $pos);
		  $running_conf = trim($running_conf);
		}

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

		if(!empty($generated_configuration))
		{
			$ret = mikrotik_generic_apply_conf($generated_configuration);
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

	function wait_until_device_is_up()
	{
	  return wait_for_device_up ($this->sd->SD_IP_CONFIG, 60, 300);
	}

}

?>
