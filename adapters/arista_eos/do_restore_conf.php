<?php

require_once 'smsd/sms_common.php';

require_once load_once('arista_eos', 'arista_eos_connection.php');
require_once load_once('arista_eos', 'arista_eos_restore_configuration.php');

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
  $ret = arista_eos_connect();
  if ($ret != SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Router connection failed (restore revision: $revision_id)");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  $conf = new arista_eos_restore_configuration($sdid);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Retrieving old configuration to restore (restore revision: $revision_id)");
  $ret = $conf->generate_from_old_revision($revision_id);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Retrieving previous configuration failed (restore revision: $revision_id)");
    arista_eos_disconnect();
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring old configuration (restore revision: $revision_id)");
  $ret = $conf->restore_conf();
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Restore failed (restore revision: $revision_id)");
    arista_eos_disconnect();
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Rebooting router (restore revision: $revision_id)");
  arista_eos_disconnect();
  $ret = $conf->wait_until_device_is_up();
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Device unreachable after restore (restore revision: $revision_id)");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

  require_once load_once('arista_eos', 'do_backup_conf.php');

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore processed (restore revision: $revision_id)");
}
catch (Exception | Error $e)
{
  arista_eos_disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), 'RESTORE', 'FAILED', "Restore failure (restore revision: $revision_id)");
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

?>