<?php
/*
 * Version: $Id$
 * Created: Jun 2, 2008
 * Available global variables
 *  $sms_sd_info        sd_info structure
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_csp            pointer to csp context to retreive usefull field(s)
 *  $sdid
 * 	$SMS_RETURN_BUF    string buffer containing the result
 */

// Generate the staging for the router


require_once 'smsd/sms_user_message.php';
require_once 'smsd/pattern.php';
require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';

require_once "$db_objects";

$net_profile = get_network_profile();
$sd = $net_profile->SD;

$staging_param['SMS_ADDRESS_IP'] = $_SERVER['SMS_ADDRESS_IP'];
$staging_param['IP_SMS_SYSLOG'] = $_SERVER['IP_SMS_SYSLOG'];
$staging_param['FTPSERVER_PUBIP'] = $_SERVER['FTPSERVER_PUBIP'];
$staging_cli = PATTERNIZETEMPLATE('staging.tpl', $staging_param);

$user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
$user_message = sms_user_message_add_json($user_message, SMS_UMN_RESULT, $staging_cli);

sms_send_user_message($sms_csp, $sdid, $user_message);

return SMS_OK;
?>