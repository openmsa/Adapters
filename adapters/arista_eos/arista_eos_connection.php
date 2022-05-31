<?php
/*
* Created: May 24, 2022
*/

// For communication with the router
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('arista_eos', 'common.php');
require_once "$db_objects";


function arista_eos_connect($ip = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null){
	global $sms_sd_ctx;

	$default_protocol = "ssh";
	
	try{
		// If port is 22, use ssh. If port is 23, use telnet. 
		// Otherwise, depend on the default protocol.
		$do_ssh = TRUE;
		$port = $port_to_use;

		if (empty($port)) {
			$network = get_network_profile();
			$SD = &$network->SD;
			
			if ($SD->SD_MANAGEMENT_PORT !== 0) {
				$port = $SD->SD_MANAGEMENT_PORT;
			} else {
				$port = 22;
			}
		}
		
		if ($port === 22) {
			$do_ssh = TRUE;
		} elseif ($port === 23) {
			$do_ssh = FALSE;
		} else {
			if ($default_protocol === "ssh") {
				$do_ssh = TRUE;
			} else {
				$do_ssh = FALSE;
			}
		}

		if ($do_ssh) {
			$sms_sd_ctx = new AristaEosSshConnection($ip, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "SSH");
		} else {
			$sms_sd_ctx = new AristaEosTelnetConnection($ip, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}
	} catch (SmsException $e) {
		try{
			$sms_sd_ctx = new AristaEosTelnetConnection($ip, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}catch (SmsException $e) {
			return ERR_SD_CONNREFUSED;
		}
	}
	return SMS_OK;
}


function arista_eos_disconnect()
{
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}


class AristaEosSshConnection extends SshConnection
{

	public function do_post_connect()
	{
		echo "***Call Arista EOS Do_post_connect***\n";
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
		$this->sendexpectone(__FILE__.':'.__LINE__, "no terminal width",'#');
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

class AristaEosTelnetConnection extends TelnetConnection
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
