<?php
/*
* Created: May 25, 2022
*
*/

// Enter Script description here

require_once 'smsd/sms_common.php';

require_once load_once('arista_eos', 'arista_eos_connection.php');
require_once load_once('arista_eos', 'arista_eost_configuration.php');


try
{
  $status_type = 'UPDATE';

  $ret = sms_sd_lock($sms_csp, $sms_sd_info);
	if ($ret !== 0)
	{
		sms_close_user_socket($sms_csp);
		sms_send_user_error($sms_csp, $sdid, "", $ret);
		return SMS_OK;
	}

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');
	sms_send_user_ok($sms_csp, $sdid, "");

	// Asynchronous mode, the user socket is now closed, the results are written in database
	sms_close_user_socket($sms_csp);

	$ret = sd_connect();
	if ($ret !== SMS_OK)
	{
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
		sms_sd_unlock($sms_csp, $sms_sd_info);
		return SMS_OK;
	}

	$conf = new AristaEosConfiguration($sdid);

	$ret = $conf->update_conf();
	if ($ret !== SMS_OK)
	{
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $SMS_OUTPUT_BUF);
		sms_sd_unlock($sms_csp, $sms_sd_info);
	  sd_disconnect();
		return SMS_OK;
	}

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
	sms_sd_unlock($sms_csp, $sms_sd_info);
}
catch(Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, ERR_SD_CMDTMOUT, $status_type, 'FAILED', '');
	sms_sd_unlock($sms_csp, $sms_sd_info);
	sd_disconnect();
	return SMS_OK;
}

return SMS_OK;

?>