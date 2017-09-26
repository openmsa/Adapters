<?php
/*
 * Version: $Id: do_staging.php 24200 2009-11-25 10:16:39Z tmt $
 * Created: Jun 2, 2008
 * Available global variables
 *  $sms_csp           pointer to csp context to respond to user
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 */

// Generate the Pre-Conf for the router

require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'common.php');
require_once load_once('netasq', 'netasq_configuration.php');

$json_msg = array();

$conf = new netasq_configuration($sdid);
$ret = $conf->staging($json_msg['9']);
if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  return SMS_OK;
}

$user_message = json_encode($json_msg);
sms_send_user_ok($sms_csp, $sdid, $user_message);

return SMS_OK;
?>