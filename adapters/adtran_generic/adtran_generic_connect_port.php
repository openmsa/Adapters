<?php
/*
 * 	Version: 0.1: adtran_generic_connect.php
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
require_once 'smsd/port_console_connection.php';
require_once load_once('adtran_generic', 'common.php');
require_once "$db_objects";

// return false if error, true if ok
function adtran_generic_connect_port($ts_ip, $ts_port, $adminpasswd = null)
{
  global $sms_sd_ctx;

  try{
  $sms_sd_ctx = new CiscoIsrPortConsoleConnection($ts_ip, null, null, $adminpasswd, $ts_port);
  	  $sms_sd_ctx->setParam("PROTOCOL", "CONSOLE");
  } catch (SmsException $e) {
  		return ERR_SD_CONNREFUSED;
  	}
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function adtran_generic_disconnect_port()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

class CiscoIsrPortConsoleConnection extends PortConsoleConnection
{
	public function do_connect()
	{
		try {
			parent::do_connect();
		}
		catch (SmsException $e) {
			throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
		}
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}


?>