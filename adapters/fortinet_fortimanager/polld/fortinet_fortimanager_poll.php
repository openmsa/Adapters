<?php

require_once 'smsd/sms_common.php';
require_once load_once('fortinet_fortimanager', 'fortinet_fortimanager_connect.php');

require_once "$db_objects";

try
{

  fortinet_fortimanager_connect();
  fortinet_fortimanager_disconnect();
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
