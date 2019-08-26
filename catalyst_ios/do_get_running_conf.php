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

// Get router configuration

require_once 'smsd/sms_common.php';

require_once load_once('catalyst_ios', 'catalyst_configuration.php');
require_once load_once('catalyst_ios', 'adaptor.php');


try {

  $ret = sd_connect();
  if ($ret == SMS_OK)
  {
    $conf = new CatalystConfiguration($sdid);
    $running_conf = $conf->get_running_conf();
  }

  if (!empty($running_conf))
  {
    sms_send_user_ok($sms_csp, $sdid, $running_conf);
  }
  else
  {
    sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
  }
  sd_disconnect();
}
catch(Exception | Error $e)
{
  sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
  sd_disconnect();
}

return SMS_OK;

?>