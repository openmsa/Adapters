<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class VersadirectorsshConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
  /*  unset($this->key);
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, "type=keygen&user={$this->sd_login_entry}&password={$this->sd_passwd_entry}", "result/key");
    $this->key = $result;
    echo "{$this->key}\n";*/
	    // first time keystone is forced by removing the endpoint

	unset($this->key);
    unset($this->endPoint);

    $cmd = "POST#/auth/token#{\"username\":\"{$this->sd_login_entry}\",\"client_secret\":\"asrevnet_123\",\"grant_type\":\"password\",\"client_id\":\"voae_rest\",\"password\":\"{$this->sd_passwd_entry}\"}";

    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
    $token = $result->xpath('//access_token');
    $this->key = $token[0];
	debug_dump($this->key);


  }

  // ------------------------------------------------------------------------------------------------
  public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
  {
    global $sendexpect_result;
    $this->send($origin, $cmd);

    if ($prompt !== 'lire dans sdctx' && !empty($prompt))
    {
      $tab[0] = $prompt;
    }
    else
    {
      $tab = array();
    }


    $this->expect($origin, $tab);

    if (is_array($sendexpect_result))
    {
      return $sendexpect_result[0];
    }
    return $sendexpect_result;
  }

  // ------------------------------------------------------------------------------------------------
  public function send($origin, $cmd)
  {
    unset($this->xml_response);
    unset($this->raw_xml);

    $delay = EXPECT_DELAY / 1000;

    // la commande $cmd est décomposée en trois  parties pour Versa : GET#/cms..#parametres creation
    $action = explode("#", $cmd);
    echo "number of action(".$cmd."):".count($action)."\n";//debug cmd
    $token = "";
    if (isset($this->key)) {
      $token = "-H \"Authorization: Bearer {$this->key}\"";
	  $header ="-H \"Content-Type: application/vnd.yang.datastore+json\" -H \"Accept: application/vnd.yang.datastore+json\"";
	}
	else {
	  $header ="-H \"Content-Type: application/json\" -H \"Accept: application/json\"";
	}

	// count the number of / in the command to classify the order
	$slash= explode("/", $action[1]);
	echo "number of slash(".$action[1]."):".count($slash)."\n";//debug cmd

	if (count($slash)>3) //cas objet
    {
		echo "==case object==\n";
        $header ="-H \"Content-Type: application/vnd.yang.data+json\" -H \"Accept: application/vnd.yang.data+json\"";
        if (strpos($action[1], ':') === false) // API contains the port
        {
 			$action[1] = 'https://' . $this->sd_ip_config . ':9183' . $action[1];
 		}
	    else
	    {
	    	$action[1] = 'https://' . $this->sd_ip_config . $action[1];
		}
        if (count($action) < 3)
        {
 			$curl_cmd = "curl --tlsv1 -sw '\nHTTP_CODE=%{http_code}' {$token} {$header} --connect-timeout {$delay} --max-time {$delay}  -k '{$action[1]}";
		}
	    else
	    {
	    	$curl_cmd = "curl --tlsv1 -X {$action[0]}  -sw '\nHTTP_CODE=%{http_code}' {$token} {$header} --connect-timeout {$delay} --max-time {$delay} -d '{$action[2]}' -k '{$action[1]}";
		}
	}
	else if (count($action) < 3)
	{
		echo "==case configuration==\n";
		$action[1] = 'https://' . $this->sd_ip_config . ':9183' . $action[1];
        $curl_cmd = "curl --tlsv1 -sw '\nHTTP_CODE=%{http_code}'  {$token} {$header} --connect-timeout {$delay} --max-time {$delay}  -k '{$action[1]}";
    }
    else
    {
    	echo "==case authentication==\n";
    	$action[1] = 'https://' . $this->sd_ip_config . ':9183' . $action[1];
        $curl_cmd = "curl --tlsv1 -sw '\nHTTP_CODE=%{http_code}' -X {$action[0]}  {$token} {$header} --connect-timeout {$delay} --max-time {$delay} -d '{$action[2]}' -k '{$action[1]}";
    }

    $curl_cmd .= "' && echo";

    echo "{$curl_cmd} \n";
    $ret = exec_local($origin, $curl_cmd, $output_array);

    if ($ret !== SMS_OK)
    {
      throw new SmsException("Call to API Failed", $ret);
    }

    $result = '';
    foreach ($output_array as $line)
    {
      if (strpos($line, '"error-message":') !==false)
      {
      	//   "error-message": "access denied",
      	//{  "errors": {    "error": [      {        "error-message": "{expecting_comma_or_curly,rsbrace}",        "error-urlpath": "/api/config/devices/device/guillaume-DataStore/config/org:orgs/org-services/guillaume/security:security/access-policies",        "error-tag": "malformed-message"      }    ]  }}
        //device answer contains an error
        echo $line." -> identified as an error\n";
        $error = substr($line, strpos($line,'ge":')+6);
        $error = substr($error, 0, strpos($error,'",'));
        throw new SmsException("Command failed on the device: $error", ERR_SD_CMDFAILED, $origin);
      }else if ($line !== 'SMS_OK')
      {
        if (strpos($line, 'HTTP_CODE') !== 0)
        {
          $result .= "{$line}\n";
        }
        else
        {
          if (strpos($line, 'HTTP_CODE=20') !== 0)
			  if ((strpos($line, 'HTTP_CODE=40') !== 0) && $action[0] !== 'DELETE') // forcer le OK pour un 404 sur un DELETE
			  {
				$cmd_quote = str_replace("\"", "'", $result);
				$cmd_return = str_replace("\n", "", $cmd_quote);
				throw new SmsException("Call to API Failed HTTP CODE = $line, $cmd_quote error", ERR_SD_CMDFAILED, $origin);
			  }
        }
      }
    }

    $array = json_decode($result, true);

    // call array to xml conversion function
    $xml = arrayToXml($array, '<root></root>');

    $this->xml_response = $xml;
    $this->raw_json = $result;

    $this->raw_xml = $this->xml_response->asXML();
    debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
  }

  // ------------------------------------------------------------------------------------------------
  public function sendCmd($origin, $cmd)
  {
    $this->send($origin, $cmd);
  }

  // ------------------------------------------------------------------------------------------------
  public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result')
  {
    global $$global_result_name;

    if (!isset($this->xml_response))
    {
      throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
    }
    $index = 0;
    if (empty($tab))
    {
      $result = $this->xml_response;
      $$global_result_name = $result;
      return $index;
    }
    foreach ($tab as $path)
    {
      $result = $this->xml_response->xpath($path);
      if (($result !== false) && !empty($result))
      {
        $$global_result_name = $result;
        return $index;
      }
      $index++;
    }

    foreach ($tab as $path)
    {
        if (strpos($this->raw_xml, $path) !== 0)
        {
             return strpos($this->raw_xml, $path);
         }
	}

    throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
  }

  // ------------------------------------------------------------------------------------------------
  public function do_store_prompt()
  {
  }

  public function get_raw_xml()
  {
    return $this->raw_xml;
  }

  public function get_raw_json()
  {
    return $this->raw_json;
  }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function versa_director_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new VersadirectorsshConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function versa_director_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>