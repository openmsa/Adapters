<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";
class VMWareVsphereRESTConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;
  private $raw_json;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    unset($this->key);

    $cmd = "POST#cis#session";
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
    $session_id = $result->xpath('//value');
    $this->key = $session_id[0];
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
//    $delay = EXPECT_DELAY / 1000;
//Wait for maximum of 10 minutes as VMware apis are not asynchronous sometimes
$delay=600;

    $action = explode("#", $cmd);
    $uri = "https://" . $this->sd_ip_config . "/rest/";
    switch($action[1]) {
    	case "appliance":
    		$uri .= "appliance/";
    		break;
    	case "cis":
    		$uri .= "com/vmware/cis/";
    		break;
    	case "content":
    		$uri .= "com/vmware/content/";
    		break;
    	case "vapi":
    		$uri .= "com/vmware/vapi/";
    		break;
    	case "vcenter":
    		$uri .= "vcenter/";
    		break;
    	default:
    		throw new SmsException("Invalid vCenter Operation type", ERR_VERB_BAD_PARAM);
    }
    
    $session_info = "";
    if (isset($this->key)) {
      $session_info = "-H \"vmware-api-session-id: {$this->key}\"";
    }
    else {
      $session_info = "--basic -u '" . $this->sd_login_entry . ":" . $this->sd_passwd_entry . "'";
    }
    
    $curl_cmd = "curl --tlsv1.2 -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} {$session_info} -H \"Content-Type: application/json\" -k '{$uri}{$action[2]}'";
    if (isset($action[3])) {
      $curl_cmd .= " -d '{$action[3]}'";
    }

    echo "{$cmd} for operation type {$action[1]}\n";

    $curl_cmd .= " && echo";
    $ret = exec_local($origin, $curl_cmd, $output_array);
    echo("\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n");
    echo $ret;
	echo("\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n");
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
            throw new SmsException("$origin: Call to API Failed $line, $cmd_quote error", ERR_SD_CMDFAILED);
          }
        }
      }
    }

    //echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
    $array = json_decode($result, true);

    // call array to xml conversion function
    $xml = arrayToXml($array, '<root></root>');

    $this->xml_response = $xml;
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
function vmware_vsphere_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new VMWareVsphereRESTConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function vmware_vsphere_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
