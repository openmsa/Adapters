<?php

/*
 * Version: $Id: do_update_conf.php 39436 2011-02-10 10:57:34Z oda $
 * Created: Jun 30, 2008
 * Available global variables
 *  $sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 *
 *  $revision_id     SVN rev id for restore
 */

// Enter Script description here
require_once 'smsd/sms_common.php';
require_once load_once('pfsense_fw', 'pfsense_fw_connect.php');
require_once load_once('pfsense_fw', 'pfsense_fw_configuration.php');
require_once load_once('pfsense_fw', 'common.php');

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
  $conf = new pfsense_fw_configuration($sdid);
  $conf_content = $conf->get_generated_conf($revision_id);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring old configuration (restore revision: $revision_id)");

  pfsense_fw_connect();

  $ret = $conf->restore_conf($conf_content, false);
  if ($ret !== SMS_OK)
  {
    throw new SmsException(get_pfsense_fw_error($SMS_OUTPUT_BUF), $ret);
  }

  pfsense_fw_disconnect(true);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

  require_once load_once('pfsense_fw', 'do_backup_conf.php');

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore processed (restore revision: $revision_id)");

  return SMS_OK;
}
catch (Exception | Error $e)
{
  pfsense_fw_disconnect();
  sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Failed restoring revision $revision_id: " . $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>
