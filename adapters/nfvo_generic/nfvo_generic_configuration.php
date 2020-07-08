<?php
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('nfvo_generic', 'adaptor.php');
require_once load_once('nfvo_generic', 'nfvo_generic_apply_conf.php');

require_once "$db_objects";

class nfvo_generic_configuration
{

	var $conf_path;

	// Path for previous stored configuration files
	var $sdid;

	// ID of the SD to update
	var $running_conf;

	// Current configuration of the router
	var $profile_list;

	// List of managed profiles
	var $previous_conf_list;

	// Previous generated configuration loaded from files
	var $conf_list;

	// Current generated configuration waiting to be saved
	var $addon_list;

	// List of managed addon cards
	var $fmc_repo;

	// repository path without trailing /
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
		$this->sd = &$net->SD;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	 * Get running configuration from the router
	 */
	function get_running_conf()
	{
		return ''; // YDU the below code doesn't work
	}

	/**
	 * Generate the general pre-configuration
	 *
	 * @param $configuration configuration
	 *        	buffer to fill
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
	 *
	 * @param $configuration configuration
	 *        	buffer to fill
	 */
	function generate_post_conf(&$configuration)
	{
		get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
		return SMS_OK;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	 */
	function build_conf(&$generated_configuration)
	{
		$ret = $this->generate_pre_conf($generated_configuration);
		if ($ret !== SMS_OK) {
			return $ret;
		}
		$ret = $this->generate($generated_configuration);
		if ($ret !== SMS_OK) {
			return $ret;
		}

		$ret = $this->generate_post_conf($generated_configuration);
		if ($ret !== SMS_OK) {
			return $ret;
		}
		return SMS_OK;
	}

	/**
	 */
	function update_conf()
	{
		$ret = $this->build_conf($generated_configuration);

		if (! empty($generated_configuration)) {
			$ret = nfvo_generic_apply_conf($generated_configuration);
		}

		return $ret;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	 */
	function provisioning()
	{
		return $this->update_conf();
	}
}

