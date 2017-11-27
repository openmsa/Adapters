<?php

/**
 * A10 Thunder aXAPI
 *
 * Configuration
 *
 */

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('a10_thunder_axapi', 'adaptor.php');
require_once load_once('a10_thunder_axapi', 'a10_thunder_axapi_apply_conf.php');


require_once "$db_objects";

class a10_thunder_axapi_configuration
{

  function get_running_conf()
  {
    return '';
  }

}

?>
