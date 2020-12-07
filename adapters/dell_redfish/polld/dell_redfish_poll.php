<?php

require_once 'smsd/sms_common.php';
require_once load_once('dell_redfish', 'dell_redfish_connect.php');

require_once "$db_objects";

try
{

  dell_redfish_connect();
  dell_redfish_disconnect();
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
