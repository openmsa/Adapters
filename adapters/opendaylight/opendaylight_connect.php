<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";
class OpenStackGenericsshConnection extends GenericConnection
{
  private $key;
  private $endPointsURL;
  private $xml_response;
  private $raw_xml;
  private $raw_json;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {

    $network = get_network_profile();
    $sd = &$network->SD;

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
  		debug_dump ( $cmd );
		unset($this->xml_response);
		unset($this->raw_xml);
		unset($this->raw_json);
		
		$delay = EXPECT_DELAY / 1000;
		
		$action = explode ( "#", $cmd );
		debug_dump ( $action );
		
		$curlCMD = "curl -u {$this->sd_login_entry}:{$this->sd_passwd_entry} -H 'Content-type: application/json' -X ";
		if ($action [0] == "GET") {
			$curlCMD = $curlCMD . "GET ";
		} else if ($action [0] == "POST") {
			$curlCMD = $curlCMD . "POST  -d \"" . $action [3] . "\" ";
		} else if ($action [0] == "DELETE") {
			$curlCMD = $curlCMD . "DELETE ";
		} else {
			debug_dump ( $action [0] . " -> unknown action" );
			throw new SmsException ( $action[0]." unknown action", ERR_SD_CMDFAILED );
		}
		$curlCMD = $curlCMD . "\"http://{$this->sd_ip_config}:{$this->sd_management_port}$action[1]\" ";
		debug_dump ( $curlCMD );
		
		$ret = exec_local ( $origin, $curlCMD, $output_array );
		debug_dump ( $output_array );
		if ($ret !== SMS_OK) {
			throw new SmsException ( "Call to API Failed", $ret );
		}
		
    $result = '';
    foreach ($output_array as $line)
    {
    	$result .= "{$line}\n";
    }
    
    //check if $result ends with SMS_OK
    debug_dump(substr($result,-7,-1), "DEVICE RESPONSE ENDS WITH: \n");
    if (substr($result,-7,-1)==="SMS_OK") {
    	$result=substr($result,0,-7);
    	debug_dump($result, "REMOVE SMS_OK \n");
    }

    

    //echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
    $result = preg_replace('/xmlns="[^"]+"/', '', $result);
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
function opendaylight_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new OpenStackGenericsshConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function opendaylight_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
