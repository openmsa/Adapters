<?php
/*
 * Version: $Id$
 * Created: Jun 18, 2015
 * Available global variables
 *  $sms_sd_info      sd_info structure
 *  $sms_csp          pointer to csp context to send response to user
 *  $sdid             id of the device
 *  $optional_params  optional parameters (<vdom-name> <cert-content-b64> )
 *  $sms_module       module name (for patterns)
 */

$router_kind='fortinet_generic';

require_once 'smsd/sms_common.php';
require_once load_once($router_kind, $router_kind . '_connect.php');
require_once load_once($router_kind, $router_kind . '_configuration.php');
require_once "$db_objects";
require_once load_once($router_kind, 'prov_license_upload.php');

try
{
	fortinet_generic_connect();	
	
	$network = get_network_profile();
	$SD = &$network->SD;
	
	$conf = new fortinet_generic_configuration($sdid);
	$datacenter_ip = $conf->get_additional_vars('DATACENTER_IP');
	
	$ret = 	prov_license_upload($sms_csp, $sdid, $sms_sd_info, 1);
	fortinet_generic_disconnect();
}
catch (SmsException $e)
{
	rmdir_recursive($tftp_dir);
	return $e->getCode();
}

return $ret;

?>

