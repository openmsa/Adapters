<?php
/*
 * Version: $Id$ Created: May 30, 2011 Available global variables $sms_csp pointer to csp context to send response to user $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s) $sms_sd_info pointer to sd_info structure $SMS_RETURN_BUF string buffer containing the result
 */

// Device adaptor
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once ( 'cisco_isr', 'cisco_isr_connect_port.php' );
require_once load_once ( 'cisco_isr', 'cisco_isr_connect.php' );
require_once load_once ( 'cisco_isr', 'cisco_isr_apply_conf.php' );
require_once load_once ( 'cisco_isr', 'cisco_isr_configuration.php' );
require_once load_once ( 'cisco_isr', 'iba_configuration.php');

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
	if (empty ( $ts_ip ) || empty ( $ts_port )) {
		$ret = cisco_isr_connect ();
	} else {
		$ret = cisco_isr_connect_port ( $ts_ip, $ts_port, $adminpasswd );
	}

	return $ret;
}

/**
 * Disconnect from device
 *
 * @param
 *        	$clean_exit
 */
function sd_disconnect($clean_exit = false, $ts_ip = null) {
	if (empty ( $ts_ip )) {
		$ret = cisco_isr_disconnect ( $clean_exit );
	} else {
		$ret = cisco_isr_disconnect_port ( $clean_exit );
	}

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

	$ret = cisco_isr_apply_conf ( $configuration, $push_to_startup );

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
	$conf = new cisco_isr_configuration ( $sdid );

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
function addon_connect($addon, $need_sd_connection = false, $clear_exising_connection = false) {
	global $sdid;
	global $adaptor_addon;
	global $adaptor_need_sd_connection;

	$addon_prefix = strtolower ( $addon );
	$addon_object = "{$addon_prefix}_object";
	global $$addon_object;

	$adaptor_addon = $addon;
	$adaptor_need_sd_connection = $need_sd_connection;

	if ($need_sd_connection) {
		$ret = sd_connect ();
		if ($ret !== SMS_OK) {
			return $ret;
		}
	}

	$conf = "{$addon_prefix}_configuration";
	if (! class_exists ( $conf )) {
		return ERR_SD_NOT_SUPPORTED;
	}
	$$addon_object = new $conf ( $sdid );
	$ret = $$addon_object->connect_addon ( $clear_exising_connection );

	if (($ret !== SMS_OK) && $need_sd_connection) {
		sd_disconnect ( true );
	}

	echo "connection to the $addon OK\n";
	return $ret;
}

/**
 * Disconnect from addon board
 */
function addon_disconnect() {
	global $adaptor_addon;
	global $adaptor_need_sd_connection;

	$addon_object = strtolower ( $adaptor_addon ) . '_object';
	global $$addon_object;

	$ret = $$addon_object->exit_addon ();

	if ($adaptor_need_sd_connection) {
		sd_disconnect ( true );
	}

	return $ret;
}

/**
 * Execute a command on addon board
 *
 * @param
 *        	$addon
 * @param
 *        	$cmd
 * @param
 *        	$need_addon_connection
 * @param
 *        	$need_sd_connection
 *
 */
function addon_execute_command($addon, $cmd, $prompt, $need_addon_connection = false, $need_sd_connection = false)
{
  $addon_prefix = strtolower($addon);
  $addon_ctx = "sms_{$addon_prefix}_ctx";
  global $$addon_ctx;
  global $sendexpect_result;

  if ($need_addon_connection)
  {
    $ret = addon_connect($addon, $need_sd_connection);
    if ($ret !== SMS_OK)
    {
      return false;
    }
  }

  $tab[0] = "(y/n)"; // in case of confirmation
  $tab[1] = $prompt;
  $index = sendexpect(__FILE__.':'.__LINE__, $$addon_ctx, $cmd, $tab, 100000);
  if ($index===0){
    $sendexpect_result = sendexpectone(__FILE__.':'.__LINE__, $$addon_ctx, 'y', $prompt);
  }

  if ($need_addon_connection)
  {
    addon_disconnect();
  }

  return $sendexpect_result;
}

function addon_apply_conf($addon, &$configuration) {
	$addon_object = strtolower ( $addon ) . '_object';
	global $$addon_object;

	$ret = addon_connect ( $addon, true, true );
	if ($ret !== SMS_OK) {
		return $ret;
	}
	$ret = $$addon_object->apply_conf ( $configuration );
	addon_disconnect ();

	return $ret;
}

?>