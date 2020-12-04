<?php

/*
 * Version: $Id: do_get_sd_conf.php 23900 2014-11-19 13:40:40Z tmt $
 * Created: Jun 11, 2014
 * Available global variables
 * 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info   sd_info structure
 *  $sdid
 *  $sms_module    module name (for patterns)
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Get router configuration, not JSON response format
require_once 'smsd/sms_common.php';

require_once load_once('intel_redfish', 'intel_redfish_connect.php');
require_once load_once('intel_redfish', 'intel_redfish_configuration.php');

try
{
  $ret = intel_redfish_connect();
  if ($ret !== SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new intel_redfish_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
  intel_redfish_disconnect();
}
catch (Exception $e)
{
    intel_redfish_disconnect();
  return $e->getCode();
}

return SMS_OK;
?>
