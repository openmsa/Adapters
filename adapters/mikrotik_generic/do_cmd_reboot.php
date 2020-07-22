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

$router_kind='mikrotik_generic';

require_once 'smsd/sms_common.php';
require_once load_once($router_kind, $router_kind . '_connect.php');
require_once load_once($router_kind, $router_kind . '_configuration.php');
require_once "$db_objects";

try
{
	mikrotik_generic_connect();		
	$ret = 	reboot($sms_csp, $sdid, $sms_sd_info, 1);
	mikrotik_generic_disconnect();
}
catch (SmsException $e)
{
	throw $e;
}

return $ret;

function reboot($sms_csp, $sdid, $sms_sd_info, $stage)
{
	global $SD;
	global $ipaddr;
	global $login;
	global $passwd;
	global $port;
	global $datacenter_ip;
	global $sms_sd_ctx;
	global $sendexpect_result;

	try
	{
		$command = "execute reboot";
		$tab[0] = "#";
		$tab[1] = "y/n)";

		$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $command, $tab);
		if ($index === -1)
		{
			throw new Exception($errormsg, ERR_LOCAL_CMD);
		}
		if ($index === 1)
		{
			unset($tab);
			$tab[0] = "";
			$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab);
			if ($index === -1)
			{
				throw new Exception($errormsg, ERR_LOCAL_CMD);
			}
			if ($index === 0)
			{
			mikrotik_generic_disconnect();
			}	
		}
	}
	catch (SmsException $e)
	{
		throw $e;
	}

	return SMS_OK;
}

?>

