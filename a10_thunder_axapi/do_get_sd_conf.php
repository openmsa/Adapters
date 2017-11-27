<?php

/**
 * A10 Thunder aXAPI
 *
 * SD conf
 *
 */

require_once 'smsd/sms_common.php';

require_once load_once('a10_thunder_axapi', 'a10_thunder_axapi_connect.php');
require_once load_once('a10_thunder_axapi', 'a10_thunder_axapi_configuration.php');

try
{
  $ret = a10_thunder_axapi_connect();
  if ($ret !== SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new a10_thunder_axapi_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
  a10_thunder_axapi_disconnect();
}
catch(Exception $e)
{
  a10_thunder_axapi_disconnect();
  return $e->getCode();
}

return SMS_OK;

?>
