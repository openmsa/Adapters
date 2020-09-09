<?php

// Enter Script description here
require_once 'smsd/sms_common.php';
require_once load_once('citrix_netscalar', 'citrix_netscalar_connect.php');
require_once load_once('citrix_netscalar', 'citrix_netscalar_configuration.php');
require_once load_once('citrix_netscalar', 'common.php');

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

  $ret = citrix_netscalar_connect();

  if ($ret != SMS_OK)
  {
    throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  $conf = new citrix_netscalar_configuration($sdid);

  $ret = $conf->update_conf();
  if ($ret !== SMS_OK)
  {
    throw new SmsException($SMS_OUTPUT_BUF, $ret);
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  citrix_netscalar_disconnect();
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  citrix_netscalar_disconnect();
}

return SMS_OK;

?>