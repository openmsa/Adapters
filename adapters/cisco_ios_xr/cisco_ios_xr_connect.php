<?php


// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once load_once('cisco_ios_xr', 'common.php');
require_once "$db_objects";


// return false if error, true if ok
function cisco_ios_xr_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  echo "===============   cisco_ios_xr_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) \n";

	try{
		$sms_sd_ctx = new CiscoIsrsshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
	    return ERR_SD_CONNREFUSED;
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function cisco_ios_xr_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
  	$tab[0] = ')#';
  	$tab[1] = $sms_sd_ctx->getPrompt();
  	$tab[2] = "[cancel]:";

	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
	if ($index === 0)
	{
		$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'end', $tab);
		if ($index === 2)
		{
			$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'no', $tab);
		}
	}
	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

class CiscoIsrsshConnection extends SshConnection
{
	public function do_post_connect()
	{
		echo "***Call cisco IOS XR do_post_connect***\n";
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
		$buffer = trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

?>