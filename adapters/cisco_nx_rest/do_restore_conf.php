<?php
/*
 * Version: $Id: do_update_conf.php 39436 2011-02-10 10:57:34Z oda $
 * Created: Jun 30, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 *
 *  $revision_id     SVN rev id for restore
 */

// Enter Script description here

require_once 'smsd/sms_common.php';

require_once load_once('cisco_nx_rest', 'me_connect.php');
require_once load_once('cisco_nx_rest', 'me_restore_configuration.php');

$status_type = 'RESTORE';

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', "Restoring revision $revision_id");

sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

// Asynchronous mode, the user socket is now closed, the results are written in database

try
{
  $restore = new device_restore_configuration($sdid);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', "Retrieving configuration to restore (restore revision: $revision_id)");

  $restore->generate_from_old_revision($revision_id);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', "Restoring configuration (restore revision: $revision_id)");

  $ret = me_connect();
  if ($ret != SMS_OK)
  {
    throw new SmsException("ME connection failed (restore revision: $revision_id)", ERR_SD_NETWORK, __FILE__ . ':' . __LINE__);
  }

  $restore->restore_conf();

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', "Waiting for the ME availability (restore revision: $revision_id)");

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', "Backuping the restored configuration (restore revision: $revision_id)");

  me_disconnect();

  $ret = backup_configuration($sdid, $sms_msg, 'CONF_FILE', '', $error);
  if ($ret != SMS_OK)
  {
    throw new SmsException("Backup failed (restore revision: $revision_id): $error", $ret, __FILE__ . ':' . __LINE__);
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', "Restore completed (restore revision: $revision_id)");
}
catch (Exception | Error $e)
{
  me_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>
