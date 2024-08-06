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
        if (isset($sd->SD_CONFIGVAR_list['REST_JSON'])) {
            $this->rest_json=trim($sd->SD_CONFIGVAR_list['REST_JSON']->VAR_VALUE);
            $this->json_path = new \JsonPath\JsonPath();
            echo  "setting REST_JSON: {$this->rest_json}\n";
        }

        if ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS") {
            $cmd = "GET##/api#{}";
        } else {
            $cmd               = "POST##/v3/auth/tokens#{\"auth\": {\"identity\": {\"methods\": [\"password\"], \"password\": {\"user\": {\"domain\": {\"name\":";
            $cmd .= "\"{$user_domain_id}\"},\"name\": \"{$this->sd_login_entry}\",\"password\": \"{$this->sd_passwd_entry}\"}}}, ";
            $cmd .= "\"scope\": {\"project\": {\"domain\": {\"name\": \"{$project_domain_id}\"}, \"id\": \"{$tenant_id}\"}}}}";
        }

        $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");

        //$endPointsURL_table = $result->xpath('//token/catalog');

        //$this->endPointsURL = $endPointsURL_table[0];
    }

    // ------------------------------------------------------------------------------------------------
    public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
    {
        echo("sendexpectone:".$cmd."\n");
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
        echo("send cmd: ".$cmd."\n");
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

        $action           = explode("#", $cmd);
        $cmd_list = preg_split('@#@', $cmd, 0, PREG_SPLIT_NO_EMPTY);
		debug_dump ( $cmd_list, "CMD_LIST\n" );

        if (isset($sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD'])) {
            $kube_auth_method = $sd->SD_CONFIGVAR_list['KUBE_AUTH_METHOD']->VAR_VALUE;
        }
        if (isset($sd->SD_CONFIGVAR_list['KUBE_TOKEN'])) {
            $kube_token       = $sd->SD_CONFIGVAR_list['KUBE_TOKEN']->VAR_VALUE;
        }

        if (($action[1] == "") && ($kube_auth_method != "KUBERNETES" && $kube_auth_method != "EKS")) {
            $url = $http_protocol . '://' . $this->sd_ip_config . ':5000' . $action[2];
        } else {
            $url = $kube_http_protocol . '://' . $this->sd_ip_config . ':' . $kube_port . $action[2];
        }

        $token = '';
        if (isset($this->key)) {
            $token = $this->key;
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
            $resp = '';
            foreach($output_array as $line) {
              if ($line !== 'SMS_OK') {
                $resp .= $line;
              }
            }
            $json = json_decode($resp, true);
            $token = $json['status']['token'];
        }

        // TODO TEST validation ACTION[] fields

        $delay = EXPECT_DELAY / 1000;
        $http_header = array();
        $http_header[] = 'Content-Type: application/json';
        // $curl_cmd is used for logs
        if ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS") {
            $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} --header \"Authorization: Bearer {$token}\" -H \"Content-Type: application/json\" -k '{$url}'";
            $http_header[] = "Authorization: Bearer {$token}";
        } else {
            $curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} --header \"X-Auth-Token: {$token}\" -H \"Content-Type: application/json\" -k '{$url}'";
            $http_header[] = "X-Auth-Token: {$token}";
        }

        if (isset($action[3]) && ($kube_auth_method == "KUBERNETES" || $kube_auth_method == "EKS")) {
            $curl_cmd .= " -d '{$action[3]}'";
            $post_fields = $action[3];
        }

        echo "{$cmd} for endPoint {$url}\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HEADER, true);
        switch($action[0]) {
          case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            break;

          case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            break;

          case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;

          default:
            // GET
            break;
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $delay);
        curl_setopt($ch, CURLOPT_TIMEOUT, $delay);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($ch);
        if ($ret === false) {
          throw new SmsException("Call to API [$curl_cmd] Failed", curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch , CURLINFO_HEADER_SIZE);
        $headers = substr($ret, 0, $header_size);
        $response_headers = http_parse_headers($headers);
        $response_body = substr($ret, $header_size);

        if ($http_code >= 300) {
          throw new SmsException("$origin: Call to API Failed = $line, $response_headers\n$response_body error", ERR_SD_CMDFAILED);
        }

        if (array_key_exists('Content-Type', $response_headers)) {
          $this->content_type = $response_headers['Content-Type'];
        }
        if (array_key_exists('X-Subject-Token', $response_headers)) {
          $this->key = $response_headers['X-Subject-Token'];
        }

        if ($this->content_type == 'application/json') {
            try {
                debug_dump($response_body, "response_body:\n");
                $array = json_decode($response_body, true);
            } catch ( Exception $e ) {
                return $e->getCode ();
            }

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
            $response    = new SimpleXMLElement($result);
        } else {
            if ($this->rest_json) {
                throw new SmsException("$origin: Response to {$curl_cmd} Failed, expected json received $result", ERR_SD_CMDFAILED);
            }
            if (empty(trim($result))) {
                $response = new SimpleXMLElement('<root></root>');
            }
            $response = new SimpleXMLElement($response_body);
        }

        $this->response = $response;

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

        debug_dump($tab, "expect TAB:\n");

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
