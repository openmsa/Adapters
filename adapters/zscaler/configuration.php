<?php
require_once 'smsd/sms_common.php';

require_once load_once('zscaler', 'adaptor.php');
require_once load_once('zscaler', 'apply_conf.php');


require_once "$db_objects";
class configuration
{

  /**
	* Constructor
	*/
  function __construct($sdid, $is_provisionning = false)
  {
  }

  /**
	* Get running configuration from the router
	*/
  function get_running_conf()
  {
  	return '';
  }

  /**
	*
	*/
  function build_conf(&$generated_configuration)
  {
    return SMS_OK;
  }

  function update_conf()
  {
    return SMS_OK;
  }

  function provisioning()
  {
    return $this->update_conf();
  }
}

?>