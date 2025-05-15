<?php

require_once 'smsd/sms_common.php';
require_once load_once('zscaler', 'connect.php');

try
{
  connect();
  disconnect();
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
