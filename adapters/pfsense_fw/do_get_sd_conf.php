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

require_once load_once('pfsense_fw', 'pfsense_fw_connect.php');
require_once load_once('pfsense_fw', 'pfsense_fw_configuration.php');

try
{
  pfsense_fw_connect();
 //global $SMS_RETURN_BUF;
  // Get the conf on the router
  $conf = new pfsense_fw_configuration($sdid);
  $SMS_RETURN_BUF = $conf->get_running_conf();
  pfsense_fw_disconnect(true);
}
catch (Exception | Error $e)
{
  return $e->getCode();
}

return SMS_OK;
?>
