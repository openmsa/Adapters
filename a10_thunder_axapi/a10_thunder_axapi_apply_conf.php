<?php

/**
 * A10 Thunder aXAPI
 *
 * Apply conf
 * 
 */

require_once 'smsd/sms_common.php';
require_once load_once('a10_thunder_axapi', 'a10_thunder_axapi_configuration.php');
require_once "$db_objects";

function a10_thunder_axapi_apply_conf($configuration)
{
  return SMS_OK;
}

?>

