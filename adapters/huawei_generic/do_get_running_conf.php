<?php
/*
 * 	Version: $Id: do_get_running_conf.php 24203 2009-11-25 10:23:30Z tmt $
 * 	Created: Jun 18, 2008
 * 	Available global variables
 * 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  	$sms_sd_info   sd_info structure
 *  	$sms_csp       pointer to csp context to send response to user
 *  	$sdid
 *  	$sms_module    module name (for patterns)
 */

// Get router configuration
require_once 'smsd/sms_common.php';

require_once load_once('huawei_generic', 'device_connect.php');
require_once load_once('huawei_generic', 'device_configuration.php');

try
{
  device_connect();
  // Get the conf on the router
  $conf = new device_configuration($sdid);
  $running_conf = $conf->get_running_conf();
  device_disconnect(true);

  if (!empty($running_conf))
  {
    sms_send_user_ok($sms_csp, $sdid, $running_conf);
  }
  else
  {
    sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
  }
}
catch (Exception | Error $e)
{
  sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;

?>
