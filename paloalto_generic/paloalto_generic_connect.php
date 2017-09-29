<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class DeviceConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
    unset($this->key);
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, "type=keygen&user={$this->sd_login_entry}&password={$this->sd_passwd_entry}", "result/key");
    $this->key = $result;
    echo "{$this->key}\n";
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
    if ($this->sd_management_port===22)
    {
      //it seems that the user didn't change the port in the UI, we need to connect using API, so use default port instead of 22
      $curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}/api/?{$cmd}";
    }
    else
    {
      $curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/api/?{$cmd}";
    }
    if (isset($this->key))
    {
      $curl_cmd .= "&key={$this->key}";
    }
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
        $result .= "{$line}\n";
      }
    }

    /*
     * Special case while fetching a license from auth-code. The response is returned as a plain string.
     * Hence, conveting into an XML format to avoid the error.
     */
    $license_success_response = "VM Device License Successfully fetched and installed. Rebooting the device due to capacity change.";
    if (strpos($result, $license_success_response) !== false) {
    	$result = "<response status=\"success\"><msg><line>". $license_success_response . "</line></msg></response>";
    }

    $this->xml_response = new SimpleXMLElement($result);
    $this->raw_xml = $this->xml_response->asXML();
    debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
  }
  
  // ------------------------------------------------------------------------------------------------
  function send_file($origin, $file)
  {
  	global $sms_sd_ctx;
  	global $SD;
  	global $operation;
  	
  	#curl -ki -F 'file=@test.xml' "https://192.168.1.101/api/?key=$KEY&type=import&category=configuration"
  	$delay = EXPECT_DELAY / 1000;
  	if ($this->sd_management_port===22)
  	{
  		//it seems that the user didn't change the port in the UI, we need to connect using API, so use default port instead of 22
  		$curl_cmd = "curl -XPOST --connect-timeout {$delay} --max-time {$delay} -F 'file=@{$file}' -k 'https://{$this->sd_ip_config}/api/?type=import&category=configuration";
  	}
  	else
  	{
  		$curl_cmd = "curl -XPOST --connect-timeout {$delay} --max-time {$delay} -F 'file=@{$file}' -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/api/?type=import&category=configuration";
  	}
  	if (isset($this->key))
  	{
  		$curl_cmd .= "&key={$this->key}";
  	}
  	$curl_cmd .= "' && echo";
  
  	$this->execute_curl_cmd($origin, $curl_cmd);
  
  	$device_file = basename($file);
  
  	#curl -ki -d "key=$KEY" -d 'type=op' --data-urlencode 'cmd=<load><config><from>test.xml</from></config></load>' "https://192.168.1.101/api/"
  	if ($this->sd_management_port===22)
  	{
  		//it seems that the user didn't change the port in the UI, we need to connect using API, so use default port instead of 22
  		$curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}/api/?type=op&cmd=<load><config><from>{$device_file}</from></config></load>";
  	}
  	else
  	{
  		$curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/api/?type=op&cmd=<load><config><from>{$device_file}</from></config></load>";
  	}
  	if (isset($this->key))
  	{
  		$curl_cmd .= "&key={$this->key}";
  	}
  	$curl_cmd .= "' && echo";
  
  	$this->execute_curl_cmd($origin, $curl_cmd);
  	
  	// commit
  	if (is_object($SD)) { // PHP Notice:  Trying to get property of non-object in
  		$model = $SD->MOD_ID;
  	}else{
  		$model = 1234567;
  	}
  	//if ($SD->MOD_ID === 136)
  	if ($model === 136)
  	{
  		$cmd = "<commit><partial><vsys><member>{$SD->SD_HOSTNAME}</member></vsys></partial></commit>";
  	}
  	else
  	{
  		$cmd = "<commit><force></force></commit>";
  	}
  	
  	if ($this->sd_management_port===22)
  	{
  		//it seems that the user didn't change the port in the UI, we need to connect using API, so use default port instead of 22
  		$curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}/api/?type=commit&cmd=".$cmd;
  	}
  	else
  	{
  		$curl_cmd = "curl -d -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/api/?type=commit&cmd=".$cmd;
  	}
  	if (isset($this->key))
  	{
  		$curl_cmd .= "&key={$this->key}";
  	}
  	$curl_cmd .= "' && echo";

  	$ret = exec_local($origin, $curl_cmd, $output_array);
  	
  	$result = '';
  	$JOB_ID="NULL";
    foreach ($output_array as $line)
    {
        $result .= "{$line}\n";
        //get jobid
        if (strpos($result, 'jobid') !== false) {
    		$JOB_ID=substr($result, strpos($result, 'jobid')+6);
    		$JOB_ID=substr($JOB_ID,0,strpos($JOB_ID, '</line>'));
		}
    }
    if($JOB_ID==="NULL"){
    	if(strpos($result, 'commit/validate is in progress') !== false){
    		//<response status="error" code="13"><msg><line>Another commit/validate is in progress. Please try again later</line></msg></response>
    		debug_dump($result, "known bug: commit already launched\n");
    	}else{
    		debug_dump($result, "SOMETHING IS GOING WRONG\n");
    		return ERR_SD_CMDFAILED;
    	}
    }else{
    	debug_dump($result, "JOB ID:".$JOB_ID."\n");
    	$result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=op&cmd='.urlencode("<show><jobs><id>{$JOB_ID}</id></jobs></show>"));
    	if (!empty($result->result))
  		{
  			do
  			{
  				sleep(2);
	  			$result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=op&cmd='.urlencode("<show><jobs><id>{$JOB_ID}</id></jobs></show>"));
	  			if ($result->result->job->status == 'ACT')
	  			{
	  				debug_dump($result->result->job->progress."%", "Commit in progress\n");
	  				//status_progress("progress {$result->result->job->progress}%", $operation);
	  			}
	  		} while ($result->result->job->status != 'FIN');
  		}
  		return SMS_OK;
    }
  	return SMS_OK;
  }
  
  // ------------------------------------------------------------------------------------------------
  function execute_curl_cmd ($origin, $curl_cmd) {
  
  	unset($this->xml_response);
  	unset($this->raw_xml);
  
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
  			$result .= "{$line}\n";
  		}
  	}
  	$this->xml_response = new SimpleXMLElement($result);
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
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function paloalto_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function paloalto_generic_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>