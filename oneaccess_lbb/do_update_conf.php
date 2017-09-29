<?php
/*
 * Version: $Id: do_update_conf.php 38235 2010-12-27 08:49:22Z tmt $
 * Created: Jun 30, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 *
 */

// Enter Script description here

require_once 'smsd/sms_common.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_configuration.php');
$script_file = "$sdid:".__FILE__;


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

	$ret = oneaccess_lbb_connect();

	if ($ret != SMS_OK)
	{
		throw new SmsException("", ERR_SD_CONNREFUSED);
	}
	$conf = new oneaccess_lbb_configuration($sdid);

	$ret = $conf->update_conf();
	if ($ret !== SMS_OK)
	{
    throw new SmsException($SMS_OUTPUT_BUF, $ret);
	}

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
	sms_sd_unlock($sms_csp, $sms_sd_info);
	oneaccess_lbb_disconnect();
}
catch(Exception $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
	oneaccess_lbb_disconnect();
}

return SMS_OK;

?>