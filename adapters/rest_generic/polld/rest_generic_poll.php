<?php
/*
 * Version: $Id$
* Created: Sep 12, 2011
* Available global variables
*/

// Enter Script description here


require_once 'smsd/sms_common.php';
require_once load_once('rest_generic', 'rest_generic_connect.php');

require_once "$db_objects";

try
{

  rest_generic_connect();
  rest_generic_disconnect();
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
