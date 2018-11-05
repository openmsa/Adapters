<?php

/*
 * Version: $Id: do_get_sd_conf.php 23900 2009-11-19 13:40:40Z tmt $
* Created: Jun 18, 2008
* Available global variables
* 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info   sd_info structure
*  $sdid
*  $sms_module    module name (for patterns)
*  $SMS_RETURN_BUF     string buffer containing the result
*/

// Get router configuration, not JSON response format

require_once 'smsd/sms_common.php';

require_once load_once('cisco_apic', 'apic_connection.php');
require_once load_once('cisco_apic', 'apic_configuration.php');

try
{
  // Get the conf on the router
  $conf = new apic_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
}
catch(Exception $e)
{
  return $e->getCode();
}

return SMS_OK;
?>
