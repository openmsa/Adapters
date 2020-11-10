<?php 


require_once 'smsd/sms_common.php';
require_once load_once('oneaccess_lbb', 'common.php');
require_once load_once('oneaccess_lbb', 'apply_errors.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_configuration.php');
require_once "$db_objects";


function oneaccess_lbb_apply_conf($configuration)
{
	global $sdid;
	global $sms_sd_info;
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $apply_errors;

	$script_file = "$sdid:".__FILE__;
	$file_name = "$sdid.cfg";

	$line_config_mode = $SD->SD_CONFIG_STEP;
	$line_config_mode = 1; // Dont allow yet the TFTP mode:

	if($line_config_mode === 1)
	{
		return apply_conf_by_lines($configuration);
	}
	else
	{
		return apply_conf_by_tftp($configuration);
	}
}

function apply_conf_by_tftp($configuration)
{
	global $sdid;
	global $sms_sd_info;
	global $script_file;

	$script_file = "$sdid:".__FILE__;
	$file_name = "$sdid.cfg";
	$in_error = false;

	$sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];

	// Save the configuration applied on the router
	save_result_file($configuration, 'conf.applied');

	// Create the file
	$local_file_name = $_SERVER['TFTP_BASE']."/".$file_name;
	$ret = save_file($configuration, $local_file_name);

	if ($ret !== SMS_OK)
	{
		throw new SmsException("TFTP Mode, saving configuration file [" . $local_file_name . "] for tftp transfert failed!", $ret);
	}

	$sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];

	$connection->send(__LINE__, "copy tftp://$sms_ip_addr/$file_name startup-config\n");

	unset($tab);
	$tab[0] = "?[Yes/press any key for no]";
	$tab[1] = "bytes copied";
	$result_id = $connection->expect(__LINE__, $tab);

	if($result_id === 0)
	{
		$connection->send(__LINE__, "Y\n");
		$result_id = $connection->expect(__LINE__, $tab);
	}
	if($result_id === 1)
	{
		$connection->reboot();
	} else {
		throw new SmsException("copy tftp failed!", ERR_SD_CMDFAILED);
	}

	return SMS_OK;
}

/*function reboot($connection)
 {
$connection->reboot();
}*/

function apply_conf_by_lines($configuration)
{
	global $sdid;
	global $sms_sd_info;
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $apply_errors;

	$sendexpect_result = "";
	$ERROR_BUFFER = '';

	$script_file = "$sdid:".__FILE__;

	// ---------------------------------------------------
	// Line by line mode configuration
	// ---------------------------------------------------
	unset($tab);
	$tab[0] = "(configure)>";
	$tab[1] = "(config)>";
	$sms_sd_ctx->sendCmd(__LINE__, "conf t");
	$sms_sd_ctx->expect(__LINE__, $tab);

	unset($tab);
	$tab[0] = $sms_sd_ctx->getPrompt();
	$tab[1] = ")>";
	$tab[2] = "]?";
	$tab[3] = "[confirm]";
	$tab[4] = "[no]:";

	save_result_file($configuration, 'conf.applied');

	$line = get_one_line($configuration);
	$SMS_OUTPUT_BUF="";
	while ($line !== false)
	{
		if (strpos($line, "!") === 0)
		{
			echo "$sdid: $line\n";
		}
		else
		{
			$sms_sd_ctx->send(__LINE__, $line . "\r\n");
			$index = $sms_sd_ctx->expect(__LINE__, $tab);
			$SMS_OUTPUT_BUF .= $sendexpect_result;
			if (($index === 2) || ($index === 3))
			{
				$sms_sd_ctx->send(__LINE__, "\r\n");
				$sms_sd_ctx->expect(__LINE__, $tab);
				$SMS_OUTPUT_BUF .= $sendexpect_result;
			}
			else if ($index === 4)
			{
				$sms_sd_ctx->send(__LINE__, "yes\r\n");
				$sms_sd_ctx->expect(__LINE__, $tab);
				$SMS_OUTPUT_BUF .= $sendexpect_result;
			}
		}
		foreach ($apply_errors as $apply_error)
		{
			if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0)
			{
				//$ERROR_BUFFER .= "!";
				//$ERROR_BUFFER .= "\n";
				$ERROR_BUFFER .= $line;
				$ERROR_BUFFER .= "\n";
				$ERROR_BUFFER .= $apply_error;
				$ERROR_BUFFER .= "\n";
				$SMS_OUTPUT_BUF = '';
			}
		}
		$line = get_one_line($configuration);
	}

	//save_result_file($result, 'conf.error');

	// resync prompt
	unset($tab);
	$tab[0] = "(config)>";
	$tab[1] = "(configure)>";
	$sms_sd_ctx->send(__LINE__, "!sync\r\n");
	//$tab[0] = "(ip-acl-ext)>";
	//$sms_sd_ctx->send(__LINE__, "ip access-list extended NETCELO_FROM_INTERNET\r\n");
	$sms_sd_ctx->expect(__LINE__, $tab);


	// Exit from config mode
	unset($tab);
	$tab[0] = $sms_sd_ctx->getPrompt(); //le prompt en mode normal : hostname>
	$tab[1] = ")>"; //le prompt en mode "conf t" : )>

	$sms_sd_ctx->send(__LINE__, "\r\n");
	$index = $sms_sd_ctx->expect(__LINE__, $tab);

	//envoi de exit\r\n tant qu'on a pas le prompt normal
	for ($i = 1; ($i <= 10) && ($index === 1); $i++)
	{
		$sms_sd_ctx->send(__LINE__, "exit\r\n");
		$index = $sms_sd_ctx->expect(__LINE__, $tab);
	}

	if (!empty($ERROR_BUFFER))
	{
		save_result_file($ERROR_BUFFER, "conf.error");
		$SMS_OUTPUT_BUF = $ERROR_BUFFER;
		sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
		return ERR_SD_CMDFAILED;
	}
	else
	{
		save_result_file("No error found during the application of the configuration", "conf.error");
	}

	unset($tab);
	$tab[0] = "Overwrite file";
	$tab[1] = $sms_sd_ctx->getPrompt();

	$sms_sd_ctx->send(__LINE__, "write mem\r\n");
	$index = $sms_sd_ctx->expect(__LINE__, $tab);
	if ($index === 0)
	{
		$sms_sd_ctx->sendexpectone(__LINE__, "Yes");
	}
	return SMS_OK;
}


?>
