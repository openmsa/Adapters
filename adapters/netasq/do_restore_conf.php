<?php
/*
 * Version: $Id: do_restore.php 39436 2011-02-10 10:57:34Z oda $
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
 *  $sms_msg         message
 *  $config_type     type of configuration (CONF_FILE or CONF_TREE)
 */

// Enter Script description here

require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_configuration.php');

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
  netasq_connect();

  $conf = new netasq_configuration($sdid);

  // Should be connected before calling restore_from_old_revision
  // After restore_from_old_revision is called, we are disconnected
  $ret = $conf->restore_from_old_revision($revision_id);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Restoring revision $revision_id failed");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Waiting the device (restore revision: $revision_id)");
  $ret = $conf->wait_until_device_is_up(60, 0);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "The device is unreachable after restoring the configuration (restore revision: $revision_id)");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  if ($conf->sd->SD_HSRP_TYPE !== 0)
  {
    netasq_connect();

    sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Synchronize the configuration on the passive node (restore revision: $revision_id)");
    $ret = $conf->ha_sync();

    netasq_disconnect();
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

  require_once load_once('netasq', 'do_backup_conf.php');

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore done (restore revision: $revision_id)");
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), 'RESTORE', 'FAILED', "Restore failure (restore revision: $revision_id)");
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>