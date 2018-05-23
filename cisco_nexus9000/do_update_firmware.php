<?php

/*
 * Version: $Id: do_update_firmware.php 23483 2011-11-03 09:11:46Z tmt $
* Created: Nov 03, 2011
* Available global variables
*  $sms_sd_info   sd_info structure
*  $sms_csp       pointer to csp context to send response to user
*  $sdid          id of the device
*  $param[1-2]	  optional parameters
*  $sms_module    module name (for patterns)
*/

// Verb JSUPDATEFIRMWARE

require_once 'smsd/sms_common.php';

require_once load_once('cisco_nexus9000', 'adaptor.php');
require_once load_once('cisco_nexus9000', 'cisco_nexus_connect.php');
require_once load_once('cisco_nexus9000', 'cisco_nexus_configuration.php');
require_once "$db_objects";

/**
 *
 */

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
  else if (empty($param))
  {
    $param = "$param ${$p}";
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

try
{
  $status_type = 'FIRMWARE';

  $ret = sms_sd_lock($sms_csp, $sms_sd_info);
  if ($ret !== 0)
  {
  	sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
    sms_send_user_error($sms_csp, $sdid, "", $ret);
    sms_close_user_socket($sms_csp);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

  // Asynchronous mode, the user socket is now closed, the results are written in database
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);

  // CASE ZTD ISR - USE STAGING ADDRESS TO DO FIRMWARE UPGRADE (NO REBOOT)
  if (preg_match("/ip=(?<ip>.*),login=(?<login>.*),pwd=(?<pwd>.*),admin_passwd=(?<admin_passwd>.*),NO_REBOOT/", $param, $matches) > 0)
  {
    $ipaddr = $matches['ip'];
    $login = $matches['login'];
    $passwd = $matches['pwd'];
    $admin_passwd = $matches['admin_passwd'];
    $port = '';
    $ret = cisco_nexus_connect($ipaddr, $login, $passwd, $admin_passwd, $port);
  }
  else
  {
    $ret = cisco_nexus_connect();
  }

  if ($ret != SMS_OK)
  {
    throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  $conf = new cisco_nexus_configuration($sdid);

  $status_message = "";
  $ret = $conf->update_firmware($param);
  cisco_nexus_disconnect();

  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
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

}
catch (Exception $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  cisco_nexus_disconnect();
  return SMS_OK;
}

return SMS_OK;

?>
