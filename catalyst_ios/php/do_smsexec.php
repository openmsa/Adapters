<?php
/*
 * Version: $Id$
 * Created: Dec 28, 2009
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $smsexec_list		list of commands
 */

// Enter Script description here


require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('catalyst_ios', 'adaptor.php');
require_once load_once('catalyst_ios', 'common.php');
require_once "$db_objects";



if (empty($smsexec_list))
{
  sms_send_user_ok($sms_csp, $sdid, '');
  return SMS_OK;
}

$network = get_network_profile();
$SD = &$network->SD;

// connect to the router
try {
	$ret = sd_connect();
	if ($ret !== SMS_OK)
	{
	  sms_log_error(__FILE__.':'.__LINE__.": sd_connect() failed\n");
	  sms_send_user_error($sms_csp, $sdid, "", $ret);
	  return $ret;
	}
	
	$result = '';
	$cmds = explode(',', $smsexec_list);
	foreach ($cmds as $cmd)
	{
	  if (!empty($specific_cmds[$cmd]))
	  {
	    // call specific function
	    $specific_cmds[$cmd]();
	  }
	  else
	  {
	    // send command
	    $result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'lire dans sdctx', 36000000);
	  }
	}
	
	sd_disconnect(true);
	
	sms_send_user_ok($sms_csp, $sdid, $result);
	
	return SMS_OK;
} catch (Exception $e) {
	sd_disconnect();
	sms_send_user_error($sms_csp, $sdid, "", $e->getCode());
	return $e->getCode();
}
?>