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

require_once load_once('catalyst_ios', 'adaptor.php');
require_once load_once('catalyst_ios', 'catalyst_configuration.php');

try
{
	$SMS_RETURN_BUF = "";
	$ret = sd_connect();
	if ($ret != SMS_OK)
	{
	  return $ret;
	}
	$conf = new CatalystConfiguration($sdid);
	$SMS_RETURN_BUF = $conf->get_running_conf();
	sd_disconnect();
}
catch(Exception $e)
{
	sd_disconnect();
	sms_log_error($e->getMessage());
	return $e->getCode();
}

return SMS_OK;

?>
