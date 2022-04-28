<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";
class NokiaVsdRESTConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;
  private $raw_json;
  private $vsd;
  private $nuage_organization;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    unset($this->key);
    unset($this->vsd);
    unset($this->nuage_organization);
    
    $network = get_network_profile();
    $sd = &$network->SD;
    $this->nuage_organization = $sd->SD_CONFIGVAR_list['NUAGE_ORGANIZATION']->VAR_VALUE;
    $this->vsd = $this->sd_ip_config;
    /**
     * POST https://{nuage_sdn_ip}:8443/nuage/api/v4_0/me
	 * -H "X-Nuage-Organization: csp" -H "Authorization: XREST dWJpcXViZTp1YmlxdWJl"
     */
    $cmd = "GET#me";
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
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
    $action[1] = "https://{$this->vsd}:8443/nuage/api/v4_0/" . $action[1];
    $authorization = "";
    if (isset($this->key)) {
    	$authorization = $this->key;
    }
    else {
		$authorization = base64_encode("{$this->sd_login_entry}:{$this->sd_passwd_entry}");
    }
    $curl_cmd = "curl -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} -H \"Accept: application/json\" -H \"Content-Type: application/json\" -H \"Authorization: XREST {$authorization}\" -H \"X-Nuage-Organization: {$this->nuage_organization}\" -k '{$action[1]}'";
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
    $result = preg_replace('/xmlns="[^"]+"/', '', $result);
    $array = json_decode($result, true);
    if (array_key_exists('APIKey', $array[0])) {
        $this->key = $array[0]['APIKey'];
        $this->key = base64_encode("{$this->sd_login_entry}:{$this->key}");
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
function nokia_vsd_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new NokiaVsdRESTConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function nokia_vsd_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>