<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";
class OpenStackKeystoneV3RESTConnection extends GenericConnection
{
  private $key;
  private $endPointsURL;
  private $xml_response;
  private $raw_xml;
  private $raw_json;

  public function do_connect()
  {
    unset($this->key);
    unset($this->endPointsURL);

    // first time keystone is forced by removing the endpoint
    unset($this->endPoint);

    $network = get_network_profile();
    $sd = &$network->SD;
    $tenant_id = $sd->SD_CONFIGVAR_list['TENANT_ID']->VAR_VALUE;
    $user_domain_id = $sd->SD_CONFIGVAR_list['USER_DOMAIN_ID']->VAR_VALUE;
    $project_domain_id = $sd->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID']->VAR_VALUE;
    $cmd = "POST##/v3/auth/tokens#{\"auth\": {\"identity\": {\"methods\": [\"password\"], \"password\": {\"user\": {\"domain\": {\"name\":";
    $cmd .= "\"{$user_domain_id}\"},\"name\": \"{$this->sd_login_entry}\",\"password\": \"{$this->sd_passwd_entry}\"}}}, ";
    $cmd .= "\"scope\": {\"project\": {\"domain\": {\"name\": \"{$project_domain_id}\"}, \"id\": \"{$tenant_id}\"}}}}";

    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");

    $endPointsURL_table = $result->xpath('//token/catalog');
    $this->endPointsURL = $endPointsURL_table[0];
  }

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

  public function send($origin, $cmd)
  {
    unset($this->xml_response);
    unset($this->raw_xml);

    $network = get_network_profile();
    $sd = &$network->SD;
    $http_protocol = $sd->SD_CONFIGVAR_list['HTTP_PROTOCOL']->VAR_VALUE;
    $endpoints_interface = $sd->SD_CONFIGVAR_list['ENDPOINTS_INTERFACE']->VAR_VALUE;

    if (empty($http_protocol)) {
       $http_protocol = "http";
    }
    echo("send: using http protocol: " . $http_protocol . "\n");

    if (empty($endpoints_interface)) {
       $endpoints_interface = "public";
    }

    echo("send: using endpoint interface: " . $endpoints_interface . "\n");


    $delay = EXPECT_DELAY / 1000;

    // MODIF LO : the command $cmd is split into 4 parts for Openstack : GET#:endpoint (nova,keystone...)#/tenants...#parameters creation
    echo("send: split command: " . $cmd . "\n");
    $action = explode("#", $cmd);

    // If no endpoint, use keystone as the default
    if ($action[1] == "")
    {
      $action[2] = $http_protocol . '://' . $this->sd_ip_config . ':5000' . $action[2];
    }
    else
    {
      // fetch the endpoint based on the Openstack component to use (nova, keystone, glance...)
      #$url_endpoint = $this->endPointsURL->xpath("./row[name='" . $action[1] . "']/endpoints/row/adminURL");
      $url_endpoint = $this->endPointsURL->xpath("./row[name='" . $action[1] . "']/endpoints/row[interface='" . $endpoints_interface . "']/url");
      // keep only the endpoint URL by removing the tenant UUID if it's present 
      $action[2] = preg_replace("/\/(AUTH_)?[a-f0-9]+$/", "/", $url_endpoint[0]) . $action[2];
    }

    $token = "";
    if (isset($this->key)) {
      $token = "-H \"X-Auth-Token: {$this->key}\"";
    }
      
    // TODO TEST ACTION[] field validation
    $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} {$token} -H \"Content-Type: application/json\" -k '{$action[2]}'";
    if (isset($action[3])) {
      $curl_cmd .= " -d '{$action[3]}'";
    }
      
    echo "{$cmd} for endPoint {$action[1]}\n";

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
            throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
          }
        }
      }
    }

    //echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
    $result = rtrim($result);
    $headers_and_response = explode("\n\n", $result);
    $headers_and_response_count = count($headers_and_response);
	if ($headers_and_response_count > 1) {
    	$raw_headers = $headers_and_response[$headers_and_response_count - 2];
    	$response_body = $headers_and_response[$headers_and_response_count - 1];
    	$response_headers = http_parse_headers($raw_headers);
    	if (array_key_exists('X-Subject-Token', $response_headers)) {
    		$this->key = $response_headers['X-Subject-Token'];
    	}
    }
    else {
    	$response_body = "";
    }
    
    $array = json_decode($response_body, true);

    // call array to xml conversion function
    $xml = arrayToXml($array, '<root></root>');

    $this->xml_response = $xml; // new SimpleXMLElement($result);
    $this->raw_json = $response_body;

    $this->raw_xml = $this->xml_response->asXML();
    debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
  }

  public function sendCmd($origin, $cmd)
  {
    $this->send($origin, $cmd);
  }

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

// return false if error, true if ok
function openstack_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new OpenStackKeystoneV3RESTConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

function openstack_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
