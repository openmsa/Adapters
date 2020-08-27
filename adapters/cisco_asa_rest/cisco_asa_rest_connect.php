<?php
/*
 * 	Version: 0.1: cisco_asa_rest_connect.php
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
require_once load_once('cisco_asa_rest', 'common.php');
require_once "$db_objects";


// return false if error, true if ok
function cisco_asa_rest_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;


  echo "===============   cisco_asa_rest_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) \n";

	try{
		$sms_sd_ctx = new CiscoAsaRestSshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		try{
			$sms_sd_ctx = new CiscoAsaRestTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
			$sms_sd_ctx->setParam("PROTOCOL", "TELNET");
		}catch (SmsException $e) {
			return ERR_SD_CONNREFUSED;
		}
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function cisco_asa_rest_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
  	$tab[0] = ')#';
  	$tab[1] = $sms_sd_ctx->getPrompt();
  	
  	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
  	$i = 0;
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

class CiscoAsaRestSshConnection extends SshConnection
{

	public function do_post_connect()
	{
		echo "***Call cisco ISR Do_post_connect***\n";
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

  function curl($rest_method, $path, $data)
  {
    $delay = EXPECT_DELAY / 1000;
    if ( empty($data) )
    {
      $curl_cmd = "curl -X$rest_method -u {$this->sd_login_entry}:{$this->sd_passwd_entry} --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}{$path}";
    }
    else
    {
      $curl_cmd = "curl -X$rest_method -u {$this->sd_login_entry}:{$this->sd_passwd_entry} --data '$data' --connect-timeout {$delay} --max-time {$delay}  -H \"Content-Type:application/json\" -k 'https://{$this->sd_ip_config}{$path}";
    }
    echo "curl_cmd: $curl_cmd";

    $curl_cmd .= "' && echo";
    $ret = exec_local(__FILE__.':'.__LINE__, $curl_cmd, $output_array);
    if ($ret !== SMS_OK)
    {
      throw new SmsException("Call to API Failed", $ret);
    }

    $strresult = '';
    foreach ($output_array as $line)
    {
      if ($line !== 'SMS_OK')
      {
        $strresult .= "{$line}\n";
      }
    }

    // TODO: Check device response error if any.  If not, convert result to xml:



    echo "CPI Device response: " . $strresult . "\n";
    $jresult = json_decode($strresult, true);
    echo "CPI Xml response: " . arrayToXml($jresult)->asXML() . "\n";

    $result = arrayToXml($jresult);

    debug_dump($result, "DEVICE RESPONSE\n");

    return $result;
  }

}

class CiscoAsaRestTelnetConnection extends TelnetConnection
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
