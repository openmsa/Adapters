<?php

require_once 'smsd/sms_common.php';
require_once load_once('hpe_redfish', 'hpe_redfish_connect.php');

require_once "$db_objects";

try
{

  hpe_redfish_connect();
  hpe_redfish_disconnect();
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
