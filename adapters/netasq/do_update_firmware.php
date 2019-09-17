<?php
/*
 * Version: $Id: do_update_firmware.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Sep 30, 2010
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 *  $param1        to backup main partition
 */

// Verb JSUPDATEFIRMWARE

require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_configuration.php');

$status_type = 'FIRMWARE';

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
  netasq_connect();

  $conf = new netasq_configuration($sdid);

  if (!empty($param1) && $param1 === 'BACKUP')
  {
    $do_backup = true;
  }
  else
  {
    $do_backup = false;
  }

  $ret = $conf->update_firmware($do_backup);

  netasq_disconnect();
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return $e->getCode();
}

sms_sd_unlock($sms_csp, $sms_sd_info);

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
}
else
{
  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
}

return $ret;

?>
