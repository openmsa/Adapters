<?php

/*
 * Available global variables
 *  $sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 *
 */
require_once 'smsd/sms_common.php';
require_once load_once('pfsense_fw', 'pfsense_fw_connect.php');
require_once load_once('pfsense_fw', 'pfsense_fw_configuration.php');
require_once load_once('pfsense_fw', 'common.php');

try
{
  $status_type = 'UPDATE';

  $ret = sms_sd_lock($sms_csp, $sms_sd_info);
  if ($ret !== 0)
  {
    sms_send_user_error($sms_csp, $sdid, "", $ret);
    sms_close_user_socket($sms_csp);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);
  // Asynchronous mode, the user socket is now closed, the results are written in database

  pfsense_fw_connect();

  $conf = new pfsense_fw_configuration($sdid);

  $ret = $conf->update_conf();
  if ($ret !== SMS_OK)
  {
    throw new SmsException($SMS_OUTPUT_BUF, $ret);
  }

  pfsense_fw_disconnect(true);
  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
}
catch (Exception | Error $e)
{
  pfsense_fw_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

return SMS_OK;

?>
