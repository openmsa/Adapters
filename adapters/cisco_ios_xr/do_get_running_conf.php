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

require_once load_once('cisco_ios_xr', 'cisco_ios_xr_connect.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_configuration.php');

try {
  $ret = cisco_ios_xr_connect();
  if ($ret !== SMS_OK)
  {
    sms_send_user_error($sms_csp, $sdid, "", ERR_SD_CONNREFUSED);
    return SMS_OK;
  }

  // Get the conf on the router
  $conf = new cisco_ios_xr_configuration($sdid);
  $ret = $conf->get_running_conf($running_conf);

  cisco_ios_xr_disconnect();

  if (!empty($running_conf))
  {
    sms_send_user_ok($sms_csp, $sdid, $running_conf);
  }
  else
  {
    sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
  }
}
catch(Exception | Error $e)
{
  sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return $ret;


?>
