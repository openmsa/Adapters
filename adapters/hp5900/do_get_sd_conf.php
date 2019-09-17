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

require_once load_once('hp5900', 'hp5900_connect.php');
require_once load_once('hp5900', 'hp5900_configuration.php');

global $ipaddr;
global $login;
global $passwd;
global $port;

try
{
  $ret = device_connect($ipaddr, $login, $passwd, $port);
  if ($ret !== SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new device_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
  device_disconnect();
}
catch(Exception $e)
{
  device_disconnect();
  return $e->getCode();
}

return SMS_OK;
?>
