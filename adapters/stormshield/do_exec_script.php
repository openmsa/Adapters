<?php
/*
 * Version: $Id$
 * Created: Oct 28, 2010
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $exec_script_name   script file name in the repository to execute
 *  $synchronous        true if synchronous mode false if asynchronous
 */

// Execute the script on the device

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'netasq_connect.php');
require_once load_once('stormshield', 'netasq_configuration.php');

$status_type = 'SCRIPT';

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

if (! $synchronous)
{
  // Asynchronous mode, the user socket is now closed, the results are written in database
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);
}

try
{
  netasq_connect();

  $conf = new netasq_configuration($sdid);

  $ret = $conf->exec_script($exec_script_name, $return_buf);

  netasq_disconnect();
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  sms_set_update_status($sms_csp, $sdid, ERR_SD_CMDTMOUT, $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  if ($synchronous)
  {
    sms_send_user_error($sms_csp, $sdid, "", $e->getCode());
    sms_close_user_socket($sms_csp);
  }
  return $e->getCode();
}

sms_sd_unlock($sms_csp, $sms_sd_info);

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $return_buf);
  if ($synchronous)
  {
    sms_send_user_error($sms_csp, $sdid, $return_buf, $ret);
    sms_close_user_socket($sms_csp);
  }
  return $ret;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
if ($synchronous)
{
  sms_send_user_ok($sms_csp, $sdid, $return_buf);
  sms_close_user_socket($sms_csp);
}

return SMS_OK;

?>