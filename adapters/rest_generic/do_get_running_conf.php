<?php

// Get router configuration

require_once 'smsd/sms_common.php';

require_once load_once('generic_rest', 'generic_rest_connect.php');
require_once load_once('generic_rest', 'generic_rest_configuration.php');

try {
    $ret = generic_rest_connect();
	if ($ret !== SMS_OK)
	{
		sms_send_user_error($sms_csp, $sdid, "", ERR_SD_CONNREFUSED);
		return SMS_OK;
	}

	// Get the conf on the router
	$conf = new generic_rest_configuration($sdid);
	$running_conf = htmlentities($conf->get_running_conf());
	generic_rest_disconnect();

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