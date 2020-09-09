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
require_once "$db_objects";


// return false if error, true if ok
function device_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;


  try{
  	$sms_sd_ctx = new HuaweisshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
  	$sms_sd_ctx->setParam("PROTOCOL", "SSH");
  } catch (SmsException $e) {
  	try{
  		$sms_sd_ctx = new HuaweiTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
  		$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
  	} catch (SmsException $e) {
  		return ERR_SD_CONNREFUSED;
  	}
  }
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function device_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx)) {
  	sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'return');
  	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'quit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

class HuaweisshConnection extends SshConnection
{
	public function do_post_connect() {
	  $tab[0] = ">";
	  $this->expect(__FILE__.':'.__LINE__,$tab);
	  sendexpectone(__FILE__.':'.__LINE__, $this, "system-view", ']');
	  sendexpectone(__FILE__.':'.__LINE__, $this, 'user-interface vty 0 4', ']');
	  sendexpectone(__FILE__.':'.__LINE__, $this, 'screen-length 0', ']');
	  sendexpectone(__FILE__.':'.__LINE__, $this, 'quit', ']');
	  sendexpectone(__FILE__.':'.__LINE__, $this, 'quit', '>');
	}

	public function do_store_prompt() {
		global $sendexpect_result;
        sendexpectone(__FILE__.':'.__LINE__, $this, "system-view", ']');
		$check = preg_match("/\[(?<prompt>[^\]]*)\]/",$sendexpect_result,$matches);
		if($check !== FALSE){
			$this->prompt = $matches['prompt'];
		}
		else{
			$this->prompt= 'HUAWEI';
		}
        sendexpectone(__FILE__.':'.__LINE__, $this, "return");
		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

class HuaweiTelnetConnection extends TelnetConnection
{
	public function do_store_prompt() {
		$this->prompt= 'HUAWEI';
		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}

?>
