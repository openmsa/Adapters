<?php

require_once 'smsd/sms_common.php';

require_once load_once('cisco_ios_xr', 'cisco_ios_xr_connect.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_configuration.php');

try
{
  $ret = cisco_ios_xr_connect();
  if ($ret !== SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new cisco_ios_xr_configuration($sdid);
  $ret = $conf->get_running_conf($SMS_RETURN_BUF);
  cisco_ios_xr_disconnect();
}
catch(Exception | Error $e)
{
  cisco_ios_xr_disconnect();
  return $e->getCode();
}

return $ret;
?>
