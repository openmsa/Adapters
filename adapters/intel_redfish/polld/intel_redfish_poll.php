<?php

require_once 'smsd/sms_common.php';
require_once load_once('intel_redfish', 'intel_redfish_connect.php');

require_once "$db_objects";

try
{

  intel_redfish_connect();
  intel_redfish_disconnect();
  return SMS_OK;
}
catch (Exception | Error $e)
{
  $msg = $e->getMessage();
  $code = $e->getCode();
  sms_log_error("connection error : $msg ($code)");
  return $code;
}
?>
