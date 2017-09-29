<?php
/*
 * Version: $Id: do_update_firmware.php
* Available global variables
*  $sms_sd_info   sd_info structure
*  $sms_csp       pointer to csp context to send response to user
*  $sdid          id of the device
*  $param[1-2]	  optional parameters
*  $sms_module    module name (for patterns)
*/

// Verb JSUPDATEFIRMWARE

require_once 'smsd/sms_common.php';
require_once load_once('catalyst_ios', 'adaptor.php');
require_once load_once('catalyst_ios', 'catalyst_connection.php');
require_once load_once('catalyst_ios', 'catalyst_configuration.php');
require_once "$db_objects";

$status_type = 'FIRMWARE';

$net_profile = get_network_profile();
$sd = &$net_profile->SD;

$param = '';

$index = 1;
$p = "param$index";
while (!empty($$p))
{
  // Parameters
  if (strpos($$p, 'file_server_addr=') !== false)
  {
    $file_server_addr = str_replace('file_server_addr=', '', $$p);
  }
  else
  {
    $param = $$p;
  }
  $index++;
  $p = "param$index";
}


if (empty($file_server_addr))
{
  // When address is not specified, the file server is the SOC
  $is_local_file_server = false;
}
else
{
  // When address is specified, the file server is a local file server
  $is_local_file_server = true;
}

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

// Asynchronous mode, the user socket is now closed, the results are written in database
sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

// CASE ZTD CATALYST - USE STAGING ADDRESS TO DO FIRMWARE UPGRADE (NO REBOOT)
if (preg_match("/ip=(?<ip>.*),login=(?<login>.*),pwd=(?<pwd>.*),admin_passwd=(?<admin_passwd>.*),NO_REBOOT/", $param, $matches) > 0)
{
	$ipaddr = $matches['ip'];
	$login = $matches['login'];
	$passwd = $matches['pwd'];
	$admin_passwd = $matches['admin_passwd'];
	$port = '';
	$ret = catalyst_connect($ipaddr, $login, $passwd, $admin_passwd, $port);
}else{
	$ret = catalyst_connect();
}

if ($ret != SMS_OK)
{
	return SMS_OK;
}

$on_error_fct = 'sd_exit';
$conf = new CatalystConfiguration($sdid);


$status_message = "";
$ret = $conf->update_firmware($param);

catalyst_disconnect();

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $status_message);
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return $ret;
}

// ask for asset
if (strpos($param, "NO_REBOOT") === false)
{
  sms_sd_forceasset($sms_csp, $sms_sd_info);
  status_progress("Wait for the asset update", $status_type);
  sleep(60);
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);

sms_sd_unlock($sms_csp, $sms_sd_info);


return SMS_OK;

/**
 *
 */
function sd_exit()
{
  global $sms_csp;
  global $sms_sd_info;
  global $sdid;
  global $status_type;

  sms_set_update_status($sms_csp, $sdid, ERR_SD_TFTP, $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  sd_disconnect(true);
}

?>