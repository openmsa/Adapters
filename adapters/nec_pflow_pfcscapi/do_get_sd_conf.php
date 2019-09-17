<?php

require_once 'smsd/sms_common.php';

require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_connect.php');
require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_configuration.php');

try
{
  $ret = nec_pflow_pfcscapi_connect();
  if ($ret !== SMS_OK)
  {
    throw new SmsException("", ERR_SD_CONNREFUSED);
  }

  // Get the conf on the router
  $conf = new nec_pflow_pfcscapi_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
  nec_pflow_pfcscapi_disconnect();
}
catch(Exception $e)
{
  nec_pflow_pfcscapi_disconnect();
  return $e->getCode();
}

return SMS_OK;

?>
