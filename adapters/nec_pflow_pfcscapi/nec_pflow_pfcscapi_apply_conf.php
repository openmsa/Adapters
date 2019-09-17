<?php

require_once 'smsd/sms_common.php';
require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_connect.php');
require_once "$db_objects";

function nec_pflow_pfcscapi_apply_conf($configuration)
{
  return SMS_OK;
}

?>

