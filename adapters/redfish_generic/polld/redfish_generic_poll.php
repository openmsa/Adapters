<?php

require_once 'smsd/sms_common.php';
require_once load_once('redfish_generic', 'redfish_generic_connect.php');

require_once "$db_objects";

try
{

  redfish_generic_connect();
  redfish_generic_disconnect();
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
