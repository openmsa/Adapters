<?php

// Get router configuration

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_fortimanager', 'fortinet_fortimanager_connect.php');
require_once load_once('fortinet_fortimanager', 'fortinet_fortimanager_configuration.php');

try {
    $ret = fortinet_fortimanager_connect();
	if ($ret !== SMS_OK)
	{
		sms_send_user_error($sms_csp, $sdid, "", ERR_SD_CONNREFUSED);
		return SMS_OK;
	}

	// Get the conf on the router
	$conf = new fortinet_fortimanager_configuration($sdid);
	$running_conf = htmlentities($conf->get_running_conf());
	fortinet_fortimanager_disconnect();

	if (!empty($running_conf))
	{
		sms_send_user_ok($sms_csp, $sdid, $running_conf);
	}
	else
	{
		sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
	}
}
catch(Exception $e)
{
	sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;


?>