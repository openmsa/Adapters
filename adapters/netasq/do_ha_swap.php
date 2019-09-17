<?php
/*
 * Version: $Id$
 * Created: Jan 12, 2011
 * Available global variables
 *  $sms_sd_info        sd_info structure
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 */

// Execute the script on the device

require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'netasq_configuration.php');


$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

try
{
  netasq_connect();

  $conf = new netasq_configuration($sdid);

  $ret = $conf->ha_swap();

  netasq_disconnect();
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  sms_sd_unlock($sms_csp, $sms_sd_info);
  sms_send_user_error($sms_csp, $sdid, '', $e->getCode());
  sms_close_user_socket($sms_csp);
  return $e->getCode();
}

sms_sd_unlock($sms_csp, $sms_sd_info);

if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, '', $ret);
  sms_close_user_socket($sms_csp);
  return $ret;
}

sms_send_user_ok($sms_csp, $sdid, '');
sms_close_user_socket($sms_csp);

return SMS_OK;
?>