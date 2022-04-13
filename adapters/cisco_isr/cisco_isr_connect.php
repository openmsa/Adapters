<?php
/*
 * 	Version: 0.1: cisco_isr_connect.php
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
require_once load_once('cisco_isr', 'common.php');
require_once "$db_objects";


// return false if error, true if ok
function cisco_isr_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;


  echo "++++++++++++++++   cisco_isr_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) \n";

	try{
		$sms_sd_ctx = new CiscoIsrsshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		try{
			$sms_sd_ctx = new CiscoIsrTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}catch (SmsException $e) {
			return ERR_SD_CONNREFUSED;
		}
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function cisco_isr_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
  	$tab[0] = ')#';
  	$tab[1] = $sms_sd_ctx->getPrompt();

  	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
  	$i=0;
  	while($index == 0 && $i < 10)
  	{
  	  $i++;
  	  sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'end', $tab);
  	}
  	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

class CiscoIsrsshConnection extends SshConnection
{
    public function do_connect() {
      global $sendexpect_result;
      $cnx_timeout = 10; // seconds

      try {
        parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout={$cnx_timeout} '{$this->sd_login_entry}@{$this->sd_ip_config}'");

        unset($tab);
        $tab[0] = 'added';

        try {
          $this->expect(__FILE__.':'.__LINE__, $tab, $cnx_timeout * 1000);
        } catch (SmsException $e) {
          throw new SmsException($e->getMessage(), $e->getCode());
        }

        if(!preg_match('/Permanently\sadded/', $sendexpect_result, $match)) {
          $this->connect_alt_port();
        }
      }
      catch (SmsException $e) {
        $this->connect_alt_port($e);
      }

      // Manage password or auto connection (ssh keys)
      unset($tab);
      $tab[0] = 'assword:'; //adding the ":" to avoid confusion about the warning that we recieve for newer IOS devices
      $tab[1] = 'Permission denied';

      $index = 0;
      foreach ($tab as $t)
      {
        if (strpos($sendexpect_result, $t) !== false){
          break;
        }
        $index++;
      }
      if ($index > 1)
      {
        $index = $this->expect(__FILE__.':'.__LINE__, $tab);
      }
      if ($index == 0) {
        $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
      }
      if ($index == 1){
        throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
      }

      echo "SSH connection established to {$this->sd_ip_config}\n";
      $this->do_post_connect();
      $this->do_store_prompt();
      $this->do_start();
    }


	public function do_post_connect()
	{
		echo "***Call cisco ISR Do_post_connect***\n";
		unset($tab);
		$tab[0] = '#';
		$tab[1] = '$';
		$tab[2] = '>';
                sms_log_info('======================');
		$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
                sms_log_info('----------------------');
                sms_log_info($result_id);
                sms_log_info('----------------------');
		if($result_id === 2) {
			$this->sendCmd(__FILE__.':'.__LINE__, "en ");
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
			$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
		}

		if($result_id !== 0) {
			throw new SmsException("Connection Failed, can't enter in Enable mode", ERR_SD_CONNREFUSED);
		}

		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal length 0",'#');
		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal width 0",'#');
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$buffer = trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

class CiscoIsrTelnetConnection extends TelnetConnection
{

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$buffer = trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

?>
