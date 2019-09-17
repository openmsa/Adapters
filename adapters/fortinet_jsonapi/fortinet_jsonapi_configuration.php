<?php

/**
 * Configuration
 *
 * Created: Dec 10, 2018
 */

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('fortinet_jsonapi', 'adaptor.php');
require_once load_once('fortinet_jsonapi', 'fortinet_jsonapi_apply_conf.php');


require_once "$db_objects";

class fortinet_jsonapi_configuration
{

  function get_running_conf()
  {
    return '';
  }

}

?>
