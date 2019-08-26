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

require_once load_once('esa', 'esa_connect.php');
require_once load_once('esa', 'esa_configuration.php');
require_once load_once('esa', 'apply_errors.php');
require_once "$db_objects";

$status_type = 'FIRMWARE';

try
{
  $network = get_network_profile();
  $SD = &$network->SD;
  if (empty($sd_ip))
  {
    $sd_ip = $SD->SD_IP_CONFIG;
  }
  if (empty($sd_login))
  {
    $sd_login = $SD->SD_LOGIN_ENTRY;
  }
  if (empty($sd_pwd))
  {
    $sd_pwd = $SD->SD_PASSWD_ENTRY;
  }

  if (empty($sd_port))
  {
    // normal provisioning (not ZTD)
    if ($SD->SD_MANAGEMENT_PORT !== 0)
    {
      $sd_port = $SD->SD_MANAGEMENT_PORT;
    }
    else
    {
      $sd_port = 22;
    }
  }
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, ERR_SD_CMDFAILED, $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  esa_disconnect();
  return SMS_OK;
}

try
{
  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

  // Asynchronous mode, the user socket is now closed, the results are written in database
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);

  esa_connect($sd_login, $sd_pwd, $sd_ip, $sd_port);
  $conf = new esa_configuration($sdid);
  $status_message = "";
  $ret = $conf->update_firmware();
  esa_disconnect(true);
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  esa_disconnect();
  return SMS_OK;
}

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

// ask for asset
sms_sd_forceasset($sms_csp, $sms_sd_info);
sleep(60);

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);
sms_sd_unlock($sms_csp, $sms_sd_info);

return $ret;
?>
