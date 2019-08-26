<?php

// Verb JSAUPDATELICENSE
require_once 'smsd/sms_common.php';

require_once load_once('juniper_srx', 'juniper_srx_connect.php');
require_once load_once('juniper_srx', 'juniper_srx_configuration.php');
require_once load_once('juniper_srx', 'apply_errors.php');
require_once "$db_objects";

$status_type = 'LICENSE';

$net_profile = get_network_profile();
$SD = &$net_profile->SD;

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

try
{
  juniper_srx_connect();
  $conf = new juniper_srx_configuration($sdid);
  $ret = $conf->update_license();
  juniper_srx_disconnect();
}
catch (Exception | Error $e)
{
  juniper_srx_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return $e->getCode();
}

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
}
else
{
  sms_sd_forceasset($sms_csp, $sms_sd_info);
  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>
