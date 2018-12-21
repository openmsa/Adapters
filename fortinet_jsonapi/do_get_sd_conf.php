<?php

/**
 *
 * SD conf
 *
 * Created: Dec 10, 2018
 */

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_jsonapi', 'fortinet_jsonapi_connect.php');
require_once load_once('fortinet_jsonapi', 'fortinet_jsonapi_configuration.php');

try
{
  $ret = device_connect();
  if ($ret !== SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new fortinet_jsonapi_configuration($sdid);
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
