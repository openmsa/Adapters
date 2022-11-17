<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";


class KubernetesGenericRESTConnection extends GenericConnection
{
    public $rest_json;
    public $json_path;

    protected $response; // either SimpleXMLElement or array, depending of rest_json

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
        $sd = &$network->SD;
        if (isset($sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD'])) {
            $kube_auth_method = $sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD']->VAR_VALUE;
            echo ("kube_auth_method: $kube_auth_method\n");
        }
        if (isset($sd->SD_CONFIGVAR_list['TENANT_ID'])) {
            $tenant_id = $sd->SD_CONFIGVAR_list['TENANT_ID']->VAR_VALUE;
            echo ("tenant_id: $tenant_id\n");
        }
        if (isset($sd->SD_CONFIGVAR_list['USER_DOMAIN_ID'])) {
            $user_domain_id    = $sd->SD_CONFIGVAR_list['USER_DOMAIN_ID']->VAR_VALUE;
            echo ("user_domain_id: $user_domain_id\n");
        }
        if (isset($sd->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID'])) {
            $project_domain_id = $sd->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID']->VAR_VALUE;
            echo ("project_domain_id: $project_domain_id\n");
        }
        if ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS") {
            $cmd = "GET##/api#{}";
        } else {
            $cmd               = "POST##/v3/auth/tokens#{\"auth\": {\"identity\": {\"methods\": [\"password\"], \"password\": {\"user\": {\"domain\": {\"name\":";
            $cmd .= "\"{$user_domain_id}\"},\"name\": \"{$this->sd_login_entry}\",\"password\": \"{$this->sd_passwd_entry}\"}}}, ";
            $cmd .= "\"scope\": {\"project\": {\"domain\": {\"name\": \"{$project_domain_id}\"}, \"id\": \"{$tenant_id}\"}}}}";
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

        if (!$this->rest_json && is_array($sendexpect_result)) {
            return $sendexpect_result[0];
        }
        return $sendexpect_result;
    }

    // ------------------------------------------------------------------------------------------------
    public function send($origin, $cmd)
    {
        unset($this->response);
        $network = get_network_profile();
        $sd = &$network->SD;

        $http_protocol = "http";
        if (isset($sd->SD_CONFIGVAR_list['HTTP_PROTOCOL'])) {
            $http_protocol = $sd->SD_CONFIGVAR_list['HTTP_PROTOCOL']->VAR_VALUE;
        }
        $kube_http_protocol = "http";
        $kube_port = 80;
        if (isset($sd->SD_CONFIGVAR_list['KUBE_HTTP_PROTOCOL'])) {
            $kube_http_protocol = $sd->SD_CONFIGVAR_list['KUBE_HTTP_PROTOCOL']->VAR_VALUE;
        }
        if (isset($sd->SD_CONFIGVAR_list['KUBE_PORT'])) {
            $kube_port          = $sd->SD_CONFIGVAR_list['KUBE_PORT']->VAR_VALUE;
        }

        $delay = EXPECT_DELAY / 1000;

        $action           = explode("#", $cmd);
        if (isset($sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD'])) {
            $kube_auth_method = $sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD']->VAR_VALUE;
        }
        if (isset($sd->SD_CONFIGVAR_list['KUBE_TOKEN'])) {
            $kube_token       = $sd->SD_CONFIGVAR_list['KUBE_TOKEN']->VAR_VALUE;
        }

        if (($action[1] == "") && ($kube_auth_method != "KUBERNETES" && $kube_auth_method != "EKS")) {
            $action[2] = $http_protocol . '://' . $this->sd_ip_config . ':5000' . $action[2];
        } else {
            $action[2] = $kube_http_protocol . '://' . $this->sd_ip_config . ':' . $kube_port . '' . $action[2];
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

        // TODO TEST validation ACTION[] fields
        $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} {$token} -H \"Content-Type: application/json\" -k '{$action[2]}'";
        if ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS") {
            $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} --header \"Authorization: Bearer {$token}\" -H \"Content-Type: application/json\" -k '{$action[2]}'";
        }


        if (isset($action[3]) && ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS")) {
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
        $result                     = preg_replace("/\"fieldsType\": \"FieldsV1\",\s+\"fieldsV1\":(.*)\s+/", "\"fieldsType\": \"FieldsV1\"", $result);
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
        echo("content_type" + $this->content_type +"\n");
        if ($this->content_type == 'application/json') {
            $array = json_decode($response_body, true);

            if ($this->rest_json) {
                $response = $array;
            } else {
                // call array to xml conversion function
                $response = arrayToXml($array, '<root></root>');

                $this->raw_json = $response_body;
            }
        } elseif ($this->content_type == 'text/plain') {
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
            if ($this->rest_json) {
                throw new SmsException("$origin: Repsonse to API {$curl_cmd} Failed, expected json received $result", ERR_SD_CMDFAILED);
            }
            if (empty(trim($result))) {
                $response = new SimpleXMLElement('<root></root>');
            }
            $xml = new SimpleXMLElement($response_body);
        }

        $this->response = $response;

        // FIN AJOUT
        debug_dump(($this->rest_json) ? $this->response : $this->response->asXML(), "DEVICE RESPONSE\n");
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

        if (!isset($this->response)) {
            throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
        }
        $index = 0;
        if (empty($tab)) {
            $$global_result_name = $this->response;
            return $index;
        }
        foreach ($tab as $path) {

            if ($this->rest_json) {
                $result = $this->json_path->find($this->response, $path);
            } else {
                $result = $this->response->xpath($path);
            }
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
