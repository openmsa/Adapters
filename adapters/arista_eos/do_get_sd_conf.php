<?php
/*
 * Created: May 25, 2022
 */

// Get router configuration, not JSON response format

require_once 'smsd/sms_common.php';

require_once load_once('arista_eos', 'adaptor.php');
require_once load_once('arista_eos', 'arista_eos_configuration.php');

try
{
	$SMS_RETURN_BUF = "";
	$ret = sd_connect();
	if ($ret != SMS_OK)
	{
	  return $ret;
	}
	$conf = new AristaEosConfiguration($sdid);
	$SMS_RETURN_BUF = $conf->get_running_conf();
	sd_disconnect();
}
catch(Exception | Error $e)
{
	sd_disconnect();
	sms_log_error($e->getMessage());
	return $e->getCode();
}

return SMS_OK;

?>
