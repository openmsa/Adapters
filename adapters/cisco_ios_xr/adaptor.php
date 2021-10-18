<?php

// Device adaptor
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once ( 'cisco_ios_xr', 'cisco_ios_xr_connect.php' );
require_once load_once ( 'cisco_ios_xr', 'cisco_ios_xr_apply_conf.php' );
require_once load_once ( 'cisco_ios_xr', 'cisco_ios_xr_configuration.php' );

require_once "$db_objects";

/**
 * Connect to device
 *
 * @param
 *        	$login
 * @param
 *        	$passwd
 * @param
 *        	$adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null, $ts_ip = null, $ts_port = null) {
	$ret = cisco_ios_xr_connect ();
	return $ret;
}

/**
 * Disconnect from device
 *
 * @param
 *        	$clean_exit
 */
function sd_disconnect($clean_exit = false, $ts_ip = null) {
	$ret = cisco_ios_xr_disconnect ( $clean_exit );
	return $ret;
}

/**
 * Apply a configuration buffer to a device
 *
 * @param
 *        	$configuration
 * @param
 *        	$need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false, $push_to_startup = false, $ts_ip = null, $ts_port = null) {
	if ($need_sd_connection) {
		$ret = sd_connect ( null, null, null, $ts_ip, $ts_port );
	}
	if ($ret != SMS_OK) {
		throw new SmsException ( "", ERR_SD_CMDTMOUT );
	}

	$ret = cisco_ios_xr_apply_conf ( $configuration, $push_to_startup );

	if (! empty ( $ts_ip )) {
		sd_save_conf ();
	}

	if ($need_sd_connection) {
		sd_disconnect ( $ts_ip );
	}

	return $ret;
}
function sd_save_conf() {
	global $sdid;
	global $sms_sd_ctx;
	$running_conf = "";
	// get and save running conf
	$conf = new cisco_ios_xr_configuration ( $sdid );

	$running_conf = $conf->get_running_conf ();

	$ret = save_result_file ( $running_conf, "running.conf" );
	return $ret;
}


/**
 * Execute a command on a device
 *
 * @param
 *        	$cmd
 * @param
 *        	$need_sd_connection
 */
function sd_execute_command($cmd, $need_sd_connection = false) {
	global $sms_sd_ctx;

	if ($need_sd_connection) {
		$ret = sd_connect ();
		if ($ret !== SMS_OK) {
			return false;
		}
	}

	$ret = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd );

	if ($need_sd_connection) {
		sd_disconnect ( true );
	}

	return $ret;
}

?>