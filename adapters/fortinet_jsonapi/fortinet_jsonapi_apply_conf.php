<?php

/**
 *
 * Apply conf
 * 
 * Created: Dec 10, 2018
 */

require_once 'smsd/sms_common.php';
require_once load_once('fortinet_jsonapi', 'fortinet_jsonapi_configuration.php');
require_once "$db_objects";

function fortinet_jsonapi_apply_conf($configuration)
{
  return SMS_OK;
}

?>

