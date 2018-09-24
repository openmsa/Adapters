<?php

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('checkpoint_r80', 'checkpoint_r80_connect.php');                                          
require_once load_once('checkpoint_r80', 'checkpoint_r80_apply_conf.php');
//require_once load_once('checkpoint_r80', 'checkpoint_r80_apply_command_delete.php');

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
	$ret = checkpoint_r80_connect($login, $passwd);

	return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
	$ret = checkpoint_r80_disconnect();

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

	$ret = checkpoint_r80_apply_conf($configuration, false);

	if ($need_sd_connection)
	{
		sd_disconnect();
	}

	return $ret;
}



/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
// function sd_apply_command_delete($configuration, $need_sd_connection = false)
// {
// 	if ($need_sd_connection)
// 	{
// 		sd_connect();
// 	}

// 	$ret = checkpoint_r80_apply_command_delete($configuration, false);

// 	if ($need_sd_connection)
// 	{
// 		sd_disconnect();
// 	}

// 	return $ret;
// }



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
		if ($ret !== SMS_OK)
		{
			return false;
		}
	}

	$ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd);

	if ($need_sd_connection)
	{
		sd_disconnect(true);
	}

	return $ret;
}

?>