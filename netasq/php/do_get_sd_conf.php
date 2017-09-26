<?php

/*
 * Version: $Id: do_get_sd_conf.php 23900 2009-11-19 13:40:40Z tmt $
* Created: Jun 18, 2008
* Available global variables
* 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info   sd_info structure
*  $sdid
*  $sms_module    module name (for patterns)
*  $SMS_RETURN_BUF     string buffer containing the result
*/

// Get router configuration, not JSON response format

require_once 'smsd/sms_common.php';

require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_configuration.php');

echo "Retrieving backup archive\n";

$conf = new netasq_configuration($sdid);

$thread_id = $conf->thread_id;

// Define a path were write the conf:
$archive_conf_path = "/opt/sms/spool/tmp/{$sdid}_{$thread_id}_conf.na";

$date = date('c');
$SMS_RETURN_BUF = "{$date}";

$ret = $conf->get_running_conf($archive_conf_path);
if ($ret !== SMS_OK)
{
  unlink($archive_conf_path);
  return $ret;
}

$target = "/opt/sms/spool/routerconfigs/{$sdid}/conf.na";
rename($archive_conf_path, $target);

$SMS_RETURN_BUF = "{$date}: Configuration is in {$target}";

return SMS_OK;
?>