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

require_once load_once('huawei_generic', 'adaptor.php');
require_once load_once('huawei_generic', 'device_connect.php');
require_once load_once('huawei_generic', 'device_configuration.php');
require_once "$db_objects";

/**
 *
 */

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
  $conf = new device_configuration($sdid);

  $status_message = "";
  $ret = $conf->update_firmware($param);

  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);

  sms_sd_unlock($sms_csp, $sms_sd_info);

}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  device_disconnect();
  return SMS_OK;
}

return SMS_OK;

?>
