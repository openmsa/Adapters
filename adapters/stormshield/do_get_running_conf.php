<?php
/*
 * Version: $Id: do_get_running.php 24200 2009-11-25 10:16:39Z tmt $
 * Created: Jun 2, 2008
 * Available global variables
 * 	  $sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  	$sms_sd_info   sd_info structure
 *  	$sms_csp       pointer to csp context to send response to user
 *  	$sdid
 *  	$sms_module    module name (for patterns)
 */

require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('stormshield', 'netasq_configuration.php');

echo "Retrieving backup archive\n";

$conf = new netasq_configuration($sdid);

$thread_id = $conf->thread_id;

// Define a path were write the conf:
$archive_conf_path = "/opt/sms/spool/routerconfigs/$sdid/conf.na";

$ret = $conf->get_running_conf($archive_conf_path);
if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
  return 0;
}

$user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
$date = date('c');
$SMS_RETURN_BUF = "{$date}";

$user_message = sms_user_message_add_json($user_message, SMS_UMN_RESULT, $SMS_RETURN_BUF);
sms_send_user_message($sms_csp, $sdid, $user_message);

return 0;
?>