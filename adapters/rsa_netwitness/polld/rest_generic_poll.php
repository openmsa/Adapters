<?php

require_once 'smsd/sms_common.php';
require_once load_once('rsa_netwitness', 'rsa_netwitness_connect.php');

require_once "$db_objects";

try
{

  rsa_netwitness_connect();
  rsa_netwitness_disconnect();
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
