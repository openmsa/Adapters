<?php

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('nec_pflow_p4_unc', 'adaptor.php');
require_once load_once('nec_pflow_p4_unc', 'nec_pflow_p4_unc_apply_conf.php');


require_once "$db_objects";

class nec_pflow_p4_unc_configuration
{

  function get_running_conf()
  {
    return '';
  }

}

?>
