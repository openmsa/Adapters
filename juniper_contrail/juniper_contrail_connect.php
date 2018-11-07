<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

// AJOUT LO
function arrayToXmlPouet($array, $rootElement = null, $xml = null)
{
  $_xml = $xml;
  
  if ($_xml === null)
  {
    $_xml = new SimpleXMLElement($rootElement !== null ? $rootElement : '<root><root/>');
  }
  
  foreach ($array as $k => $v)
  {
    if (is_numeric($k))
    {
      $k = 'W';
    }
    if (is_array($v))
    { //nested array
      arrayToXmlPouet($v, $k, $_xml->addChild($k));
    }
    else
    {
      $_xml->addChild($k, $v);
    }
  }
  return $_xml;
}
//
class DeviceConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;
  private $raw_json;
  
  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    // MODIF LO
    // unset($this->key);
    // $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, "type=keygen&user={$this->sd_login_entry}&password={$this->sd_passwd_entry}", "result/key");
    // $this->key = $result;
    // echo "{$this->key}\n";
    // FIN MODIF LO
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
    // MODIF LO
    

    //$curl_cmd = "curl -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}/api/?{$cmd}";
    // MODIF LO : la commande $cmd est décomposée en trois parties pour COntrail : GET#virtual-network#parametres creation
    $action = explode("#", $cmd);
    // TODO TEST validité champ ACTION[]
    if (isset($action[2]))
      $curl_cmd = "curl  -sw '\nHTTP_CODE=%{http_code}' -X {$action[0]} -H \"Content-Type: application/json\" --connect-timeout {$delay} --max-time {$delay} -d '{$action[2]}' -k 'http://{$this->sd_ip_config}:8082/{$action[1]}";
    else
      $curl_cmd = "curl  -sw '\nHTTP_CODE=%{http_code}' -X {$action[0]} -H \"Content-Type: application/json\" --connect-timeout {$delay} --max-time {$delay} -k 'http://{$this->sd_ip_config}:8082/{$action[1]}";
    
    echo "{$cmd}\n";
    
    //if (isset($this->key))
    // {
    //  $curl_cmd .= "&key={$this->key}";
    //}
    // FIN MODIF LO
    $curl_cmd .= "' && echo";
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
          if (strpos($line, 'HTTP_CODE=200') !== 0)
          {
            $cmd_quote = str_replace("\"", "'", $result);
            $cmd_return = str_replace("\n", "", $cmd_quote);
            throw new SmsException("Call to API Failed HTTP CODE = $line, $cmd_quote error", ERR_SD_CMDFAILED, $origin);
          }
        }
      }
    }
    
    //echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
    $array = json_decode($result, true);
    
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
function juniper_contrail_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;
  
  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function juniper_contrail_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
