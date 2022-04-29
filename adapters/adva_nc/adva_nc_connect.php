<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class DeviceConnection extends GenericConnection
{
    private $xml_response;
    private $raw_xml;
    
    // ------------------------------------------------------------------------------------------------
    public function do_connect()
    {
        $cmd ="tapi/v2/notifications/alarms";
        $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);
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
        echo "\n**************".$cmd."****************************\n";
        $delay = EXPECT_DELAY / 1000;
        $header =  $this->sd_login_entry.":".$this->sd_passwd_entry;
        
        $action = explode("#", $cmd);
        
        if(!isset($action[1]))
        {
            $method = "GET";
        }
        else
        {
            $method = $action[1];
        }
        
        
        $curl_cmd = "curl -X{$method} -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --header 'content-type: application/json' -u {$header} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/cxf/{$action[0]}";
        
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
                        $cmd_return = str_replace("\n", "", $cmd_quote);
                        throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
                    }
                }
            }
        }
        $te = str_replace("\\","",$result);
        $te = str_replace("\"{","{",$te);
        $te = str_replace("}\"","}",$te);
        
        $ae = json_decode($te,true);
        
        
        //        $array = json_decode($result, true);
        $array = json_decode(preg_replace('/xmlns="[^"]+"/', '', $te), true);
        
        
        
        // call array to xml conversion function
        $xml = arrayToXml($array, '<root></root>');
        
        
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
        $this->xml_response = new SimpleXMLElement(preg_replace('/xmlns="[^"]+"/', '', $result));
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
function adva_nc_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
    global $sms_sd_ctx;
    
    $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);
    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function adva_nc_disconnect()
{       
    return SMS_OK;
}

?>
