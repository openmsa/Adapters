<?php
/*
 * Version: $Id$
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Device adaptor

$script_file = "$sdid:".__FILE__;
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_configuration.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_apply_conf.php');

require_once "$db_objects";

//$sms_sd_ctx = NULL;

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = NULL, $passwd = NULL, $adminpasswd = NULL)
{
	$ret = oneaccess_lbb_connect(NULL, $login, $passwd, $adminpasswd);
	
	return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
	
	$ret = oneaccess_lbb_disconnect();
	return $ret;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false)
{

	if ($need_sd_connection)
	{
		sd_connect();
	}

	$ret = oneaccess_lbb_apply_conf($configuration);


	if ($need_sd_connection)
	{
		sd_disconnect(true);
	}

	// Now, backup the configuration to svn
	/* $ret = exec_local($script_file.':'.__LINE__, "/opt/sms/bin/save_router_conf \"Brownfield BACKUP\" $sdid CONF_FILE", $output);
	if ($ret != SMS_OK)
	{
		sms_log_error(" Save router configuration failed!");
	} */

	return $ret;
}

/**
 * Execute a command on a device
 * @param  $cmd
 * @param  $need_sd_connection
 */
function sd_execute_command($cmd, $need_sd_connection = false)
{
	global $sms_sd_ctx;

	if ($need_sd_connection)
	{
		$ret = sd_connect();
	}

	$ret = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, $cmd);

	if ($need_sd_connection)
	{
		sd_disconnect(true);
	}

	return $ret;
}

?>
