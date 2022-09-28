<?php

require_once 'smsd/sms_common.php';
/*
require_once load_once('kubernetes_generic', 'kubernetes_generic_connect.php');

require_once "$db_objects";

try
{

  kubernetes_generic_connect();
  kubernetes_generic_disconnect();
}
catch (Exception | Error $e)
{
  $msg = $e->getMessage();
  $code = $e->getCode();
  sms_log_error("connection error : $msg ($code)");
  return $code;
}
*/
return SMS_OK;
?>
