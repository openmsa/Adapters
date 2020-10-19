<?php
/*
 * 	Version: 0.1: fujitsu_ipcom_connect.php
*  	Created: Jun 7, 2012
*  	Available global variables
*  	$sms_sd_ctx        	pointer to sd_ctx context to retreive usefull field(s)
*  	$sms_sd_info        	sd_info structure
*  	$sms_csp            	pointer to csp context to send response to user
*  	$sdid
*  	$sms_module         	module name (for patterns)
*  	$SMS_RETURN_BUF    	string buffer containing the result
*/

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once load_once('fujitsu_ipcom', 'common.php');
require_once "$db_objects";


// return false if error, true if ok
function fujitsu_ipcom_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;


  echo "===============  fujitsu_ipcom_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) \n";

	try{
		$sms_sd_ctx = new FujitsuIpcomsshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		try{
			$sms_sd_ctx = new FujitsuIpcomTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}catch (SmsException $e) {
			return ERR_SD_CONNREFUSED;
		}
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function fujitsu_ipcom_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
  	$tab[0] = ')#';
  	$tab[1] = $sms_sd_ctx->getPrompt();
    echo "==============================$tab[1]\n"; 	
  	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
  	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

class FujitsuIpcomsshConnection extends SshConnection
{

	public function do_post_connect()
	{
		echo "***Call fujitsu IPCOM Do_post_connect***\n";
		unset($tab);
		$tab[0] = '#';
		$tab[1] = '$';
		$tab[2] = '>';
		$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

		if($result_id === 2) {
			$this->sendCmd(__FILE__.':'.__LINE__, "admin ");
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
			$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
		}

		if($result_id !== 0) {
			throw new SmsException("Connection Failed, can't enter in Enable mode", ERR_SD_CONNREFUSED);
		}

		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal pager disable",'#');
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'configure', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

class FujitsuIpcomTelnetConnection extends TelnetConnection
{

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'configure', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

?>
