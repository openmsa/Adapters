<?php
/*
 * Version: $Id: do_get_running_conf.php 24203 2009-11-25 10:23:30Z tmt $
 * Created: Jun 18, 2008
 * Available global variables
 * 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 */

// Get router configuration (JSON format)

require_once 'smsd/sms_common.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_configuration.php');

$script_file = "$sdid:".__FILE__;

try
{
	oneaccess_lbb_connect();
	$conf = new oneaccess_lbb_configuration($sdid);
	$running_conf = $conf->get_running_conf();
	oneaccess_lbb_disconnect();
}
catch(Exception $e)
{
  oneaccess_lbb_disconnect();
	sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
	return SMS_OK;
}

sms_send_user_ok($sms_csp, $sdid, $running_conf);
return SMS_OK;

?>