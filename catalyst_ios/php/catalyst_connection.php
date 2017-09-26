<?php
/*
 * Version: $Id: switch300_connection.php 48177 2011-09-26 14:52:10Z cpi $
* Created: Sep 26, 2011
* Available global variables
*  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info        sd_info structure
*  $sms_csp            pointer to csp context to send response to user
*  $sdid
*  $sms_module         module name (for patterns)
*/

// For communication with the router
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('catalyst_ios', 'common.php');
require_once "$db_objects";


function catalyst_connect($ip = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null){
	global $sms_sd_ctx;
	try{
		$sms_sd_ctx = new CatalystSshConnection($ip, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		try{
			$sms_sd_ctx = new CatalystTelnetConnection($ip, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}catch (SmsException $e) {
			return ERR_SD_CONNREFUSED;
		}
	}
	return SMS_OK;
}


function catalyst_disconnect()
{
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}


class CatalystSshConnection extends SshConnection
{

	public function do_post_connect()
	{
		echo "***Call Catalyst Do_post_connect***\n";
		unset($tab);
		$tab[0] = '#';
		$tab[1] = '$';
		$tab[2] = '>';
		$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

		if($result_id === 2) {
			$this->sendCmd(__FILE__.':'.__LINE__, "en ");
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
			$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
		}

		if($result_id !== 0) {
			throw new SmsException("Connection Failed, can't enter in Enable mode", ERR_SD_CONNREFUSED);
		}

		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal length 0",'#');
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

class CatalystTelnetConnection extends TelnetConnection
{

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}



?>