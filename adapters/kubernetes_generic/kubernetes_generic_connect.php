<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";


class KubernetesGenericRESTConnection extends GenericConnection
{
   private $key;
   private $endPointsURL;
   private $xml_response;
   private $raw_xml;
   private $raw_json;
   
   // ------------------------------------------------------------------------------------------------
   public function do_connect()
   {
       unset($this->key);
       unset($this->endPointsURL);
       
       // first time keystone is forced by removing the endpoint
       unset($this->endPoint);
       
       $network = get_network_profile();
       $sd =& $network->SD;
       $kube_auth_method  = $sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD']->VAR_VALUE;
       $tenant_id         = $sd->SD_CONFIGVAR_list['TENANT_ID']->VAR_VALUE;
       $user_domain_id    = $sd->SD_CONFIGVAR_list['USER_DOMAIN_ID']->VAR_VALUE;
       $project_domain_id = $sd->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID']->VAR_VALUE;
       $cmd               = "POST##/v3/auth/tokens#{\"auth\": {\"identity\": {\"methods\": [\"password\"], \"password\": {\"user\": {\"domain\": {\"name\":";
       $cmd .= "\"{$user_domain_id}\"},\"name\": \"{$this->sd_login_entry}\",\"password\": \"{$this->sd_passwd_entry}\"}}}, ";
       $cmd .= "\"scope\": {\"project\": {\"domain\": {\"name\": \"{$project_domain_id}\"}, \"id\": \"{$tenant_id}\"}}}}";
       if ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS") {
           $cmd = "GET##/api#{}";
       }
       
       $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
       
       $endPointsURL_table = $result->xpath('//token/catalog');
       
       $this->endPointsURL = $endPointsURL_table[0];
   }
   
   // ------------------------------------------------------------------------------------------------
   public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
   {
       global $sendexpect_result;
       $this->send($origin, $cmd);
       
       if ($prompt !== 'lire dans sdctx' && !empty($prompt)) {
           $tab[0] = $prompt;
       } else {
           $tab = array();
       }
       
       $this->expect($origin, $tab);
       
       if (is_array($sendexpect_result)) {
           return $sendexpect_result[0];
       }
       return $sendexpect_result;
   }
   
   // ------------------------------------------------------------------------------------------------
   public function send($origin, $cmd)
   {
       unset($this->xml_response);
       unset($this->raw_xml);
       $network = get_network_profile();
       $sd =& $network->SD;
       
       $http_protocol = $sd->SD_CONFIGVAR_list['HTTP_PROTOCOL']->VAR_VALUE;
       if (empty($http_protocol)) {
           $http_protocol = "http";
       }
       
       $kube_http_protocol = $sd->SD_CONFIGVAR_list['KUBE_HTTP_PROTOCOL']->VAR_VALUE;
       $kube_fqdn          = $sd->SD_CONFIGVAR_list['KUBE_FQDN']->VAR_VALUE;
       $kube_port          = $sd->SD_CONFIGVAR_list['KUBE_PORT']->VAR_VALUE;
       if (empty($kube_http_protocol)) {
           $kube_http_protocol = "http";
       }
       
       $delay = EXPECT_DELAY / 1000;
       
       $action           = explode("#", $cmd);
       $kube_auth_method = $sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD']->VAR_VALUE;
       $kube_token       = $sd->SD_CONFIGVAR_list['KUBE_TOKEN']->VAR_VALUE;

       
		if(isset($kube_fqdn))
		{
			$ip_address = $kube_fqdn;
		}
		else
		{
			$ip_address = $this->sd_ip_config;
		}

       if (($action[1] == "") && ($kube_auth_method != "KUBERNETES" && $kube_auth_method != "EKS")) {
           $action[2] = $http_protocol . '://' . $this->sd_ip_config . ':5000' . $action[2];
       } else {
           $action[2] = $kube_http_protocol . '://' . $ip_address . ':' . $kube_port . '' . $action[2];
       }
       
       $token = "";
       if (isset($this->key)) {
           $token = "-H \"X-Auth-Token: {$this->key}\"";
       }
       
       if (!empty($kube_token)) {
           $token = $sd->SD_CONFIGVAR_list['KUBE_TOKEN']->VAR_VALUE;
       }
       
       if ($kube_auth_method == "EKS") {
           $region       = $sd->SD_CONFIGVAR_list['region']->VAR_VALUE;
           $cluster_id   = $sd->SD_CONFIGVAR_list['cluster_id']->VAR_VALUE;

           putenv("AWS_ACCESS_KEY_ID=$this->sd_login_entry");
           putenv("AWS_SECRET_ACCESS_KEY=$this->sd_passwd_entry");
           $get_token_cmd = "/usr/local/bin/aws eks get-token --cluster-id {$cluster_id} --region {$region}";
           $ret = exec_local($origin, $get_token_cmd, $output_array);
           sms_log_debug(15, "Get Token Response: " . $output_array);
           if ($ret !== SMS_OK) {
            throw new SmsException("Failed to get Token", $ret);
           }
           $json = json_decode($output_array[0], true);
           $token = $json['status']['token'];
       }
       
       // TODO TEST validité champ ACTION[]
       $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} {$token} -H \"Content-Type: application/json\" -k '{$action[2]}'";
       if ($kube_auth_method == "KUBERNETES"|| $kube_auth_method == "EKS") {
           $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} --header \"Authorization: Bearer {$token}\" -H \"Content-Type: application/json\" -k '{$action[2]}'";
       }
       
       
       if (isset($action[3]) && ($kube_auth_method == "KUBERNETES"|| $kube_auth_method == "EKS")) {
           $curl_cmd .= " -d '{$action[3]}'";
       }
       
       echo "{$cmd} for endPoint {$action[1]}\n";
       
       $curl_cmd .= " && echo";
       $ret = exec_local($origin, $curl_cmd, $output_array);
       
       if ($ret !== SMS_OK) {
           throw new SmsException("Call to API Failed", $ret);
       }
       
       $result = '';
       
       foreach ($output_array as $line) {
           if ($line !== 'SMS_OK') {
               if (strpos($line, 'HTTP_CODE') !== 0) {
                   $result .= "{$line}\n";
               } else {
                   if (strpos($line, 'HTTP_CODE=20') !== 0) {
                       $cmd_quote  = str_replace("\"", "'", $result);
                       $cmd_return = str_replace("\n", "", $cmd_quote);
                       throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
                   }
               }
           }
       }
       $result                     = preg_replace("/: {\s+}/", ": {}", $result);
       //echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
       $result                     = rtrim($result);
       $result                     = preg_replace('/xmlns="[^"]+"/', '', $result);
       $headers_and_response       = explode("\n\n", $result);
       $headers_and_response_count = count($headers_and_response);
       if ($headers_and_response_count > 1) {
           $raw_headers      = $headers_and_response[0];
           $response_body    = $headers_and_response[1];
           $response_headers = http_parse_headers($raw_headers);
           if (array_key_exists('Content-Type', $response_headers)) {
               $this->content_type = $response_headers['Content-Type'];
               unset($headers_and_response[0]);
               $response_body = join("\n\n", $headers_and_response);
           }
           if (array_key_exists('X-Subject-Token', $response_headers)) {
               $this->key = $response_headers['X-Subject-Token'];
           }
       } else {
           $response_body = "";
       }
       
       if ($this->content_type == 'application/json') {
            $array = json_decode($response_body, true);

            // remove "fieldsType" and "fieldsV1" as it breaks xml structure
            $items = $array["items"];
            if (isset($items)) {
                foreach ($items as $i => $item) {
                    $managedFields = $item["metadata"]["managedFields"];
                    if (isset($managedFields)) {
                        foreach ($managedFields as $j => $managedField) {
                            if (isset($managedField["fieldsType"])) {
                                unset($array["items"][$i]["metadata"]["managedFields"][$j]["fieldsType"]);
                            }
                            if (isset($managedField["fieldsV1"])) {
                                unset($array["items"][$i]["metadata"]["managedFields"][$j]["fieldsV1"]);
                            }
                        }   
                    }
                }
            }
           
           // call array to xml conversion function
           $xml = arrayToXml($array, '<root></root>');
           
           $this->raw_json = $response_body;
       } elseif ($this->content_type == 'text/plain') {
           $response_body    = preg_replace("/\"fieldsType\": \"FieldsV1\",\s+\"fieldsV1\":(.*)\s+/", "\"fieldsType\": \"FieldsV1\"", $response_body);
           $result_in_array  = explode("\n", $response_body);
           $result_in_string = "";
           $i                = 1;
           foreach ($result_in_array as &$value) {
               $value            = "<line_$i>$value</line_$i>";
               $result_in_string = $result_in_string . $value;
               $i++;
           }
           $result = "<root>$result_in_string</root>";
           $xml    = new SimpleXMLElement($result);
       } else {
           $xml = new SimpleXMLElement($response_body);
       }
       
       $this->xml_response = $xml; // new SimpleXMLElement($result);
       
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
       
       if (!isset($this->xml_response)) {
           throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
       }
       $index = 0;
       if (empty($tab)) {
           $result              = $this->xml_response;
           $$global_result_name = $result;
           return $index;
       }
       foreach ($tab as $path) {
           
           $result = $this->xml_response->xpath($path);
           if (($result !== false) && !empty($result)) {
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
function kubernetes_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
   global $sms_sd_ctx;
   
   $sms_sd_ctx = new KubernetesGenericRESTConnection($sd_ip_addr, $login, $passwd, $port_to_use);
   return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function kubernetes_generic_disconnect()
{
   global $sms_sd_ctx;
   $sms_sd_ctx = null;
   return SMS_OK;
}
