<?php

require_once 'smsd/sms_common.php';
require_once load_once('nec_pflow_p4_unc', 'nec_pflow_p4_unc_configuration.php');
require_once "$db_objects";

function nec_pflow_p4_unc_apply_conf($configuration)
{
  return SMS_OK;
}

?>

