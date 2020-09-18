<?php

require_once 'smsd/sms_common.php';
require_once load_once('juniper_rest', 'juniper_rest_connect.php');

require_once "$db_objects";

try
{

  juniper_rest_connect();
  juniper_rest_disconnect();
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
