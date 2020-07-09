<?php
/*
 * Version: $Id: do_update_conf.php 39436 2011-02-10 10:57:34Z oda $
 * Created: Jun 30, 2008
 * Available global variables
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 *	$SMS_RETURN_BUF    string buffer containing the result
 *
 *  $revision_id     SVN rev id for restore
 */

// Enter Script description here

require_once 'smsd/sms_common.php';

require_once load_once('brocade_vyatta', 'adaptor.php');

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring revision $revision_id");

sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

// Asynchronous mode, the user socket is now closed, the results are written in database

try
{
  sd_connect();

  $conf = new device_configuration($sdid);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Retrieving old configuration to restore (restore revision: $revision_id)");
  $ret = $conf->generate_from_old_revision($revision_id);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Retrieving previous configuration failed (restore revision: $revision_id)");
    sd_disconnect();
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring old configuration (restore revision: $revision_id)");
  $ret = $conf->restore_conf();
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Restore failed (restore revision: $revision_id)");
    sd_disconnect();
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }
  sd_disconnect();

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

  require_once load_once('brocade_vyatta', 'do_backup_conf.php');

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore processed (restore revision: $revision_id)");
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), 'RESTORE', 'FAILED', "Restore failure (restore revision: $revision_id)");
  sd_disconnect();
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>