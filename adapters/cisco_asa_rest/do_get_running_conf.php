<?php

// Get router configuration

require_once 'smsd/sms_common.php';

require_once load_once('cisco_asa_rest', 'cisco_asa_rest_connect.php');
require_once load_once('cisco_asa_rest', 'cisco_asa_rest_configuration.php');

try {
	$ret = cisco_asa_rest_connect();
	if ($ret !== SMS_OK)
	{
		sms_send_user_error($sms_csp, $sdid, "", ERR_SD_CONNREFUSED);
		return SMS_OK;
	}

	// Get the conf on the router
	$conf = new cisco_asa_rest_configuration($sdid);
	$running_conf = htmlentities($conf->get_running_conf());
	cisco_asa_rest_disconnect();

	if (!empty($running_conf))
	{
		sms_send_user_ok($sms_csp, $sdid, $running_conf);
	}
	else
	{
		sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
	}
}
catch(Exception | Error $e)
{
	sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;


?>
