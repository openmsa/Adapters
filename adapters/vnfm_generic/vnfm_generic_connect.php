<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class Nfvo_connection extends GenericConnection
{

        private $endPointsURL;

        private $xml_response;

        private $raw_xml;

        private $raw_json;

        // ------------------------------------------------------------------------------------------------
        public function do_connect()
        {
                //$this->endPointsURL = $endPointsURL_table[0];
                //Adding Oauth2.0 implementation
                $data = "";

                $network = get_network_profile();
                $sd = &$network->SD;

                if (isset($sd->SD_CONFIGVAR_list['AUTH_MODE'])) {
                        $auth_mode = trim($sd->SD_CONFIGVAR_list['AUTH_MODE']->VAR_VALUE);
                }
                $this->auth_mode = $auth_mode;
                if($auth_mode == 'oauth_v2'){
                        if (!isset($sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH'])) {
                                throw new SmsException ( __FILE__ . ':' . __LINE__." missing value for config var SIGNIN_REQ_PATH" , ERR_SD_CMDFAILED);
                        }
                        $this->sign_in_req_path = $sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH']->VAR_VALUE;
                        echo  "vnfm_generic_connect: setting SIGNIN_REQ_PATH to: {$sms_sd_ctx->sign_in_req_path}\n";
                }
                if (isset($sd->SD_CONFIGVAR_list['TOKEN_XPATH'])) {
                        $token_xpath = trim($sd->SD_CONFIGVAR_list['TOKEN_XPATH']->VAR_VALUE);
                        $this->token_xpath = $token_xpath;
                }
                $this->protocol = "https";
                if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
                        $this->protocol=trim($sd->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE);
                }
                echo  "vnfm_generic_connect: setting HTTP protocol to: {$this->protocol}\n";

                if($this->auth_mode != "auth-key")
                {
                        unset ( $this->key );

                        if($this->auth_mode == "oauth_v2" )
                        {
                                $data = "grant_type=client_credentials&client_id=$this->sd_login_entry&client_secret=$this->sd_passwd_entry";
                                //$data = json_encode ( $data );
                                $cmd = "POST#{$this->sign_in_req_path}#{$data}";
                                $result = $this->sendexpectone ( __FILE__ . ':' . __LINE__, $cmd );
                                debug_dump($this->token_xpath, "do_connect result: \n");
                                // extract token
                                $this->key = (string)($result->xpath($this->token_xpath)[0]);
                                debug_dump($this->key, "TOKEN\n");
                        }
                        else
                        {
                                $this->endPointsURL = $endPointsURL_table[0];
                        }


                }
        }

        // ------------------------------------------------------------------------------------------------
        public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
        {
                global $sendexpect_result;
                $this->send($origin, $cmd);

                if ($prompt !== 'lire dans sdctx' && ! empty($prompt)) {
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
                $sd = &$network->SD;

                #Get the VNFM http port number from ME configuration variable.
                $http_port = $sd->SD_CONFIGVAR_list['HTTP_PORT']->VAR_VALUE;

                if (empty($http_port)) {
                        $http_port = '8080';
                }

                #Get the VNFM Sol003 API version.
                $sol003_api_version = $sd->SD_CONFIGVAR_list['SOL003_VERSION']->VAR_VALUE;

                if (empty($sol003_api_version)) {
                        $sol003_api_version = '2.6.1';
                }

                $delay = EXPECT_DELAY / 1000;

                $action = explode("#", $cmd);

                //Add if oauth
                if($this->auth_mode == "oauth_v2" && !isset($this->key)){
                        $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} -H \"Version: 2.6.1\" -k '{$action[1]}'";
                        if (isset($action[2])) {
                                $curl_cmd .= " -d '{$action[2]}'";
                        }
                }else if($this->auth_mode == "oauth_v2" && isset($this->key)){
                        $H = trim("Authorization: Bearer");
                        $headers .= " -H '{$H} {$this->key}'";
                        $action[2]=preg_replace('/\/\//', '/', $action[2]);
                        $curl_cmd = "curl --tlsv1.2 -i" . " -X {$action[0]} -sw '\nHTTP_CODE=%{http_code}' {$headers} --connect-timeout {$delay} --max-time {$delay} -k '{$this->protocol}://{$this->sd_ip_config}:{$http_port}{$action[2]}'";
                        if (isset($action[3])) {
                                $curl_cmd .= " -d '{$action[3]}'";
                        }

                }
                else{
                        // SI pas de endpoints, on prend keystone par defaut.
                        // if ($action[1] == "")
                        // {
                        $action[2]=preg_replace('/\/\//', '/', $action[2]);
                        $action[2] = $this->protocol.'://' . $this->sd_ip_config . ':' . $http_port . $action[2];
                        // }

                        // TODO TEST validité champ ACTION[]
                        $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' -u {$this->sd_login_entry}:{$this->sd_passwd_entry} --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} -H \"Version: {$sol003_api_version}\" -H \"Content-Type: application/json\" -k '{$action[2]}'";
                        if (isset($action[3])) {
                                $curl_cmd .= " -d '{$action[3]}'";
                        }

                        echo "{$cmd} for endPoint {$action[1]}\n";
                }
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
                                                $cmd_quote = str_replace("\"", "'", $result);
                                                $cmd_return = str_replace("\n", "", $cmd_quote);
                                                throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
                                        }
                                }
                        }
                }

                echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
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
                } else {
                        $response_body = "";
                }

                $response_body = preg_replace('/xmlns="[^"]+"/', '', $response_body);
                $array = json_decode($response_body, true);

                // call array to xml conversion function
                $xml = arrayToXml($array, '<root></root>');

                $this->xml_response = $xml; // new SimpleXMLElement($result);
                $this->raw_json = $response_body;

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

                if (! isset($this->xml_response)) {
                        throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
                }
                $index = 0;
                if (empty($tab)) {
                        $result = $this->xml_response;
                        $$global_result_name = $result;
                        return $index;
                }
                foreach ($tab as $path) {

                        $result = $this->xml_response->xpath($path);
                        if (($result !== false) && ! empty($result)) {
                                $$global_result_name = $result;
                                return $index;
                        }
                        $index ++;
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
function vnfm_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
        global $sms_sd_ctx;

        $sms_sd_ctx = new Nfvo_connection($sd_ip_addr, $login, $passwd, $port_to_use);
        return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function vnfm_generic_disconnect()
{
        global $sms_sd_ctx;
        $sms_sd_ctx = null;
        return SMS_OK;
}

