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
        $network = get_network_profile();
        $SD = &$network->SD;
        $ref = $SD->SD_EXTERNAL_REFERENCE;
        unset($this->key);
        $data = array( 
	        'user'=> $this->sd_login_entry,
            'password'=> $this->sd_passwd_entry,
            'continue-last-session' => 'false',
            'session-description' => 'session initiated by MSA adapter',
            'session-name' => date("Y-m-d").' - '.$ref
        );
        
        $data = json_encode($data);
        
        $cmd = "login' -d '".$data;
        $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);
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
        echo "\nSENDING COMMAND: \n $cmd \n";
        $delay = EXPECT_DELAY / 1000;
        
        
        $header = "";
        
        if(isset($this->key))
        {
            $header = "-H 'X-chkp-sid: ".$this->key."'";
        }
        
        $curl_cmd = "curl -XPOST -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} -H 'Content-Type: application/json' {$header} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/web_api/{$cmd}";
        
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
                    if (strpos($line, 'HTTP_CODE=20') !== 0)
                    {
                        $cmd_quote = str_replace("\"", "'", $result);
                       // $cmd_return = str_replace("\n", "", $cmd_quote);
                        throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
                    }
                }
            }
        }
        
        $array = json_decode($result, true);
        if(isset($array['sid']))
        {
            $this->key = $array['sid'];
            echo "\nKEY :\n". $this->key." \n";
        }
        
        // call array to xml conversion function
        $xml = arrayToXml($array, '<root></root>');
        
        $this->xml_response = $xml; // new SimpleXMLElement($result);
        $this->raw_json = $result;
        
        // FIN AJOUT
        $this->raw_xml = $this->xml_response->asXML();
        //debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
    }
    
    // ------------------------------------------------------------------------------------------------
   /*
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
    */
    
    
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
function checkpoint_r80_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
    global $sms_sd_ctx;
    
    $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);
    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function checkpoint_r80_disconnect()
{
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    // PUBLISH
    $publish_cmd = "publish' -d '{}";
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $publish_cmd);  
    $publish_result =  $sms_sd_ctx->raw_json;
    echo "\nPUBLISH RESULT: \n ".$publish_result." \n";
    $array = json_decode($publish_result, true);
    if(isset($array['task-id'])) {
        $task_id = $array['task-id'];
        echo "\nTASK-ID :\n" . $task_id ." \n";

        echo "\nWAIT FOR PUBLISH TASK TO BE FINISHED\n";
        $i = 0;
        $task_status = "in progress";
        do {    
                $showtask_cmd = "show-task' -d '$publish_result";
                $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $showtask_cmd);  
                $showtask_result =  $sms_sd_ctx->raw_json;
                echo "\nSHOW TASK RESULT: \n ".$showtask_result." \n";
                $showtask_result_array = json_decode($showtask_result, true);
                $task_status =  $showtask_result_array['tasks'][0]['status'];
                echo "\nSHOW TASK STATUS: \n <".$task_status."> \n";
                sleep (1);
                $i++;
                if ($i == 20) {
                    echo "\nERROR: PUBLISH TASK FAILED TO EXECUTE WITHIN 20 sec. \n ";
                    throw new SmsException("ERROR: PUBLISH TASK $task_id FAILED TO EXECUTE WITHIN 20 sec", ERR_SD_CMDTMOUT, $origin);
                }
        } while ($task_status == "in progress");
    }
    // LOGOUT
    $logout_cmd = "logout' -d'{}";
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $logout_cmd);
    $sms_sd_ctx = null;
    $SMS_RETURN_BUF=null;
    return SMS_OK;
}

?>
