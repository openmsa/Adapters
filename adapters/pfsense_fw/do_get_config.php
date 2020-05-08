<?php
/*
 * Version: $Id: do_get_config.php 36331 2010-10-26 15:01:12Z tmt $
 * Created: Jul 31, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 */

// Get generated configuration for the router
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('pfsense_fw', 'pfsense_fw_configuration.php');

$generated_configuration = '';

$conf = new pfsense_fw_configuration($sdid);

$ret = $conf->build_conf($generated_configuration);
if ($ret !== SMS_OK)
{
  return $ret;
}

sms_send_user_ok($sms_csp, $sdid, $generated_configuration);

return SMS_OK;

?>
