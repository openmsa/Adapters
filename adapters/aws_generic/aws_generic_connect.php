<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

require 'autoload.php';
use Aws\Iam\IamClient;
use Aws\Ec2\Ec2Client;
use Aws\S3\S3Client;

class AWSSDKConnection extends GenericConnection
{
  private $key;
  private $secret;
  private $raw_xml;
  private $raw_json;
  private $xml_response;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    $network = get_network_profile();
    $sd = &$network->SD;
    $this->key = $this->sd_login_entry;
    $this->secret = $this->sd_passwd_entry;
    $this->region = $sd->SD_HOSTNAME;
    
    $cmd = "Aws\Ec2\Ec2Client#describeInstances#{ \"MaxResults\" : 5 }";
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");    

    $cmd = "Aws\Ec2\Ec2Client#describeVpcs#";
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
    // MODIF LO
    
    $action = explode("#", $cmd);

    echo "AWS Client : {$action[0]}\n";
    echo "AWS Action : {$action[1]}\n";
    
    $result = "";
    $array = array();
    try {
   		$clientClass = $action[0];
   		
   		//use $clientClass;
   		$client = $clientClass::factory(array(
   			'key' => $this->key,
   			'secret' => $this->secret,
   			'region' => $this->region
   		));

    	$awsAction = $action[1];
       
      if (isset($action[2])) {
        echo "AWS Action Params : {$action[2]}\n";
        $awsActionParams = json_decode($action[2], true);
        echo "AWS Action Params (after json_decode) : $awsActionParams\n";
        $result = $client->$awsAction($awsActionParams);
   		}
   		else {
   			$result = $client->$awsAction();
   		}
      echo "AWS Action result: \n$result\n";
      $result = preg_replace('/xmlns="[^"]+"/', '', $result);

   		$array = $result->toArray();
    } catch (Exception | Error $e) {
	    throw new SmsException("Call to SDK command failed Exception: $e", ERR_SD_CMDFAILED);
    } 
    
    // call array to xml conversion function
    $xml = arrayToXml($array, '<root></root>');
    
    $this->xml_response = $xml; // new SimpleXMLElement($result);
    $this->raw_json = json_encode($array);
    
    // FIN AJOUT
    $this->raw_xml = $this->xml_response->asXML();
    debug_dump($this->raw_xml, "AWS RESPONSE\n");
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
function aws_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new AWSSDKConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function aws_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
