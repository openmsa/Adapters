<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class NokiaCloudbandRESTConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;
  private $raw_json;
  private $cbnd;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    unset($this->key);
    unset($this->cbnd);
    
    $network = get_network_profile();
    $sd = &$network->SD;
    if (!empty($sd->SD_CONFIGVAR_list['CBND_FQDN']->VAR_VALUE)) {
		$this->cbnd = $sd->SD_CONFIGVAR_list['CBND_FQDN']->VAR_VALUE;
    }
    else {
    	$this->cbnd = $this->sd_ip_config;
    }
    /**
     * POST http://${CBND_BACKEND_SERVER_NAME}:9080/auth/realms/CloudBandNetworkDirector/protocol/openid-connect/token
	 * Content-Type: application/x-www-form-urlencoded
 	 * grant_type=password&username=${CBND_USER_NAME}&password=${CBND_PASSWORD}&client_id=cbnd
     */
    $cmd = "POST#auth/realms/CloudBandNetworkDirector/protocol/openid-connect/token";

    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
    #$this->key = $this->raw_xml->xpath('//root/access_token');
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

    // MODIF LO : la commande $cmd est décomposée en quatre  parties pour Cloudband : GET#:endpoint (nova,keystone...)#/tenants...#parametres creation
    $action = explode("#", $cmd);

    $delay = 50;
    $token = "";
    if (isset($this->key)) {
      $token = "-H \"Authorization: bearer {$this->key}\" -H \"Content-Type: application/json\"";
      $action[1] = "http://{$this->cbnd}:9080/api/cbnd/" . $action[1];
    }
    else {
	  $token = "-H \"Content-Type: application/x-www-form-urlencoded\"";
	  $action[1] = "http://{$this->cbnd}:9080/" . $action[1];
	  $action[2] = "grant_type=password&username={$this->sd_login_entry}&password={$this->sd_passwd_entry}&client_id=cbnd";
    }
    
    $curl_cmd = "curl --tlsv1.2 -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} {$token} -k '{$action[1]}'";
    if (isset($action[2])) {
      $curl_cmd .= " -d '{$action[2]}'";
    }
    
    // FIN MODIF LO
    $curl_cmd .= " && echo";
    $ret = exec_local($origin, $curl_cmd, $output_array);

    if ($ret !== SMS_OK)
    {
      throw new SmsException("Call to API Failed", $ret);
    }

    $result = '';
    foreach ($output_array as $line)
    {
      if ($line !== 'SMS_OK')
      {
        if (strpos($line, 'HTTP_CODE') !== 0)
        {
          $result .= "{$line}\n";
        }
        else
        {
          if (strpos($line, 'HTTP_CODE=20') !== 0)
          {
            $cmd_quote = str_replace("\"", "'", $result);
            $cmd_return = str_replace("\n", "", $cmd_quote);
            throw new SmsException("$origin: Call to API Failed HTTP CODE = $line, $cmd_quote error", ERR_SD_CMDFAILED);
          }
        }
      }
    }
    
    #echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
    $result = trim(preg_replace('/\s+/', '', $result));
    $result = str_replace('\r', '', $result);
    $result = str_replace('\n', '', $result);
    $result = str_replace('\t', '', $result);
    $result = preg_replace('/xmlns="[^"]+"/', '', $result);

    $array = json_decode($result, true);
    if (array_key_exists('access_token', $array)) {
        $this->key = $array['access_token'];
    }
    
    // call array to xml conversion function
    $xml = arrayToXml($array, '<root></root>');

    $this->xml_response = $xml; // new SimpleXMLElement($result);
    $this->raw_json = $result;
    
    // FIN AJOUT
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
      throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
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

    throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
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
function nokia_cloudband_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new NokiaCloudbandRESTConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function nokia_cloudband_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>