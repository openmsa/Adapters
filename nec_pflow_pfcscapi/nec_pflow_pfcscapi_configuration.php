<?php

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('nec_pflow_pfcscapi', 'adaptor.php');
require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_apply_conf.php');

require_once "$db_objects";

class nec_pflow_pfcscapi_configuration
{

  function get_running_conf()
  {
    return '';
  }

}

?>

