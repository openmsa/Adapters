<?php
/*
 * Version: $Id$
 * Created: Dec 3, 2009
 * Available global variables
 *  $sdid              ID of the SD
 *  $sms_csp           pointer to csp context to send response to user
 *  $sms_module        module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 *  $sms_errno         error number when callback failed
 *  $db_objects        script containing objects extracted from database
 */

// Enter Script description here
$script_file = "$sdid:".__FILE__;
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');

require_once "$db_objects";

try
{
	if (empty($smsexec_list))
	{
		sms_send_user_ok($sms_csp, $sdid, '');
		return SMS_OK;
	}

	oneaccess_lbb_connect();

	$result = '';
	$cmds = explode(',', $smsexec_list);
	foreach ($cmds as $cmd)
	{
  	$result .= $sms_sd_ctx->sendexpectone(__LINE__, $cmd, 'lire dans sdctx', 600000);
	}
	oneaccess_lbb_disconnect();
}
catch(Exception $e)
{
  oneaccess_lbb_disconnect();
	sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
	return SMS_OK;
}

sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;

?>