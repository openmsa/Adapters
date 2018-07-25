<?php

// Enter Script description here
require_once 'smsd/sms_common.php';
require_once load_once ( 'netconf_generic', 'netconf_generic_connect.php' );
require_once load_once ( 'netconf_generic', 'netconf_generic_configuration.php' );
require_once load_once ( 'netconf_generic', 'common.php' );

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
	$conf = new netconf_generic_configuration ( $sdid );
	$conf_content = $conf->get_generated_conf ( $revision_id );

	sms_set_update_status ( $sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring old configuration (restore revision: $revision_id)" );

	netconf_generic_connect ();

	$ret = $conf->restore_conf ( $conf_content, false );
	if ($ret !== SMS_OK)
	{
	  throw new SmsException ( get_wsa_error ( $SMS_OUTPUT_BUF ), $ret );
	}

	netconf_generic_disconnect ( true );

	sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

	require_once load_once('netconf_generic', 'do_backup_conf.php');

	sms_set_update_status ( $sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore processed (restore revision: $revision_id)" );

	return SMS_OK;
}
catch ( Exception $e )
{
	netconf_generic_disconnect ();
	sms_set_update_status ( $sms_csp, $sdid, ERR_SD_CMDTMOUT, 'RESTORE', 'FAILED', "Failed restoring revision $revision_id: " . $e->getMessage () );
	sms_sd_unlock ( $sms_csp, $sms_sd_info );
	return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
?>