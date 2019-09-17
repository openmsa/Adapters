<?php
/*
 * 	Version: 0.1: veex_rtu_connect.php
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
function veex_rtu_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  try
  {
    $sms_sd_ctx = new VeexRTUTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
    $sms_sd_ctx->setParam("PROTOCOL", "TELNET");
  }
  catch (SmsException $e)
  {
    debug_dump($e);
    return ERR_SD_CONNREFUSED;
  }
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function veex_rtu_disconnect()
{
  $sms_sd_ctx = null;
  return SMS_OK;
}

class VeexRTUTelnetConnection extends TelnetConnection
{
	public function do_connect() {
	
		if (!empty($this->sd_management_port) && $this->sd_management_port != 22)
		{
			parent::connect("telnet {$this->sd_ip_config} $this->sd_management_port");
		}
		else
		{
			parent::connect("telnet {$this->sd_ip_config}");
		}
	
		unset($tab);
		$tab[0] = 'Connected';
	
		try {
			$this->expect(__FILE__.':'.__LINE__, $tab, 2000);
		} catch (SmsException $e) {
			throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
		}
	
		$net_profile = get_network_profile();
		$sd = &$net_profile->SD;
	
		unset($tab);
		$tab[0] = ">";
		$tab[1] = "login:";
		$tab[2] = "assword:";
		
		sendexpectone(__FILE__ . ':' . __LINE__, $this, $this->sd_login_entry, 'assword:');
		sendexpectone(__FILE__ . ':' . __LINE__, $this, $this->sd_passwd_entry, '>');

		$this->prompt = ">";	
		echo "Telnet connection established to {$this->sd_ip_config}\n";
	}

}

?>
