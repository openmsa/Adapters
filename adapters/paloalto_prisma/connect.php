<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class connect extends GenericConnection {

  private $auth_fqdn;
  private $auth_header;
  private $conn_timeout;
  private $http_header_list;
  private $http_header_type;
  private $json_path;
  private $key;
  private $protocol;
  private $response;
  private $sd_hostname;
  private $sign_in_req_path;
  private $token_jsonpath;
  private $tsg_id;
  private $site_id;
  private $element_id;

  public function __construct($ip = null, $login = null, $passwd = null, $admin_password = null, $port = null)
  {
    $network = get_network_profile();
    $sd = &$network->SD;

    if (isset($sd->SD_CONFIGVAR_list['AUTH_FQDN'])) {
      $this->auth_fqdn = trim($sd->SD_CONFIGVAR_list['AUTH_FQDN']->VAR_VALUE);
    } else {
      $this->auth_fqdn = 'auth.apps.paloaltonetworks.com';
    }
    echo "connect: setting AUTH_FQDN to: {$this->auth_fqdn}\n";

    if (isset($sd->SD_CONFIGVAR_list['AUTH_HEADER'])) {
      $this->auth_header = trim($sd->SD_CONFIGVAR_list['AUTH_HEADER']->VAR_VALUE);
    } else {
      $this->auth_header = 'Authorization: Bearer';
    }
    echo "connect: setting authentication header to: {$this->auth_header}\n";

    if (isset($sd->SD_CONFIGVAR_list['CONN_TIMEOUT']))
    {
      $this->conn_timeout = trim($sd->SD_CONFIGVAR_list['CONN_TIMEOUT']->VAR_VALUE);
    } else {
      $this->conn_timeout = EXPECT_DELAY / 1000;
    }
    echo "connect: setting HTTP timeout to: {$this->conn_timeout}\n";

    if (isset($sd->SD_CONFIGVAR_list['HTTP_HEADER'])) {
      $http_header_str = trim($sd->SD_CONFIGVAR_list['HTTP_HEADER']->VAR_VALUE);
    } else {
      $http_header_str = 'Content-Type: application/x-www-form-urlencoded';
    }
    echo "connect: setting HTTP header to: " . print_r($this->http_header_list, true) . "\n";

    $this->http_header_list = array(
        'AUTH' => array (
            'POST' => array($http_header_str),
        ),
        'DATA' => array(
            'GET' => array('Accept: application/json'),
            'POST' => array('Content-Type: application/json', 'Accept: application/json'),
            'PUT' => array('Content-Type: application/json', 'Accept: application/json'),
            'DELETE' => array('Accept: application/json'),
        ),
    );
    $this->http_header_type = 'AUTH';
    $this->json_path = new \JsonPath\JsonPath();

    if (isset($sd->SD_CONFIGVAR_list['AUTH_KEY'])) {
      $this->key = trim($sd->SD_CONFIGVAR_list['AUTH_KEY']->VAR_VALUE);
    } else {
      $this->key = null;
    }
    echo "connect: setting AUTH_KEY to: {$this->key}\n";

    if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
      $this->protocol = trim($sd->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE);
    } else {
      $this->protocol = 'https';
    }
    echo "connect: setting HTTP protocol to: {$this->protocol}\n";

    $this->response = null;
    $this->sd_hostname = empty($sd->SD_HOSTNAME) ? 'api.strata.paloaltonetworks.com' : $sd->SD_HOSTNAME;
    $this->sd_ip_config = empty($ip) ? $sd->SD_IP_CONFIG : $ip;
    $this->sd_login_entry = empty($login) ? $sd->SD_LOGIN_ENTRY : $login;

    if (isset($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT'])) {
      $this->sd_management_port = trim($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT']->VAR_VALUE);
    } else {
      $this->sd_management_port = empty($port) ? $sd->SD_MANAGEMENT_PORT : $port;
    }
    echo "connect: using management port: {$this->sd_management_port}\n";

    $this->sd_passwd_entry = empty($passwd) ? $sd->SD_PASSWD_ENTRY : $passwd;

    if (isset($sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH'])) {
      $this->sign_in_req_path = trim($sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH']->VAR_VALUE);
    } else {
      $this->sign_in_req_path = '/auth/v1/oauth2/access_token';
    }
    echo "connect: setting SIGNIN_REQ_PATH to: {$this->sign_in_req_path}\n";

    if (isset($sd->SD_CONFIGVAR_list['TOKEN_JSONPATH'])) {
      $this->token_jsonpath = trim($sd->SD_CONFIGVAR_list['TOKEN_JSONPATH']->VAR_VALUE);
    } else {
      $this->token_jsonpath = '$.token';
    }
    echo "connect: setting TOKEN_JSONPATH to: {$this->token_jsonpath}\n";

    if (isset($sd->SD_CONFIGVAR_list['TSG_ID'])) {
      $this->tsg_id = trim($sd->SD_CONFIGVAR_list['TSG_ID']->VAR_VALUE);
    }
    echo "connect: setting TSG_ID to: {$this->tsg_id}\n";

    if (isset($sd->SD_CONFIGVAR_list['SITE_ID'])) {
      $this->site_id = trim($sd->SD_CONFIGVAR_list['SITE_ID']->VAR_VALUE);
    }
    echo "connect: setting SITE_ID to: {$this->site_id}\n";

    if (isset($sd->SD_CONFIGVAR_list['ELEMENT_ID'])) {
      $this->element_id = trim($sd->SD_CONFIGVAR_list['ELEMENT_ID']->VAR_VALUE);
    }
    echo "connect: setting ELEMENT_ID to: {$this->element_id}\n";
  }

  public function do_connect() {

    $data = "grant_type=client_credentials&scope=tsg_id:{$this->tsg_id}&client_id=$this->sd_login_entry&client_secret=$this->sd_passwd_entry";
    $cmd = "POST#{$this->sign_in_req_path}#{$data}";
    $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);

    // extract token
    unset($this->key);
    $this->key = $this->response['access_token'];
    $this->http_header_type = 'DATA';
    // Add the bearer token
    $bearer_token_header = "{$this->auth_header} {$this->key}";
    $this->http_header_list[$this->http_header_type]['GET'][] = $bearer_token_header;
    $this->http_header_list[$this->http_header_type]['POST'][] = $bearer_token_header;
    $this->http_header_list[$this->http_header_type]['PUT'][] = $bearer_token_header;
    $this->http_header_list[$this->http_header_type]['DELETE'][] = $bearer_token_header;
  }

  public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true) {
    global $sendexpect_result;
    $this->send($origin, $cmd);

    if (($prompt !== 'lire dans sdctx') && !empty($prompt)) {
      $tab[0] = $prompt;
    } else {
      $tab = array();
    }

    $this->expect($origin, $tab);

    return $sendexpect_result;
  }


  public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result') {
    global $$global_result_name;

    if (isset($this->response)) {
      $index = 0;
      if (empty($tab)) {
        $$global_result_name = $this->response;
        return $index;
      }
      foreach ($tab as $path) {
        $result = $this->json_path->find($this->response, $path);
        if (($result !== false) && !empty($result)) {
          $$global_result_name = $result;
          return $index;
        }
        $index++;
      }
      throw new SmsException("cmd failed, $tab[0] not found", ERR_LOCAL_NOT_FOUND, $origin);
    } else {
      $$global_result_name = json_decode('{}', true);
    }
  }

  public function do_store_prompt() {
  }

  public function send($origin, $rest_cmd) {
    $this->response = null;
    echo "send(): rest_cmd = $rest_cmd\n";
    $cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);
    debug_dump($cmd_list, "CMD_LIST\n");

    $http_op = $cmd_list[0];
    $rest_path = '';
    if (count($cmd_list) > 1) {
      $rest_path = $cmd_list[1];
    }

    if (isset($this->key)) {
      $ip_address = $this->sd_hostname;
    } else {
      $ip_address = $this->auth_fqdn;
    }

    $url = "{$this->protocol}://{$ip_address}:{$this->sd_management_port}{$rest_path}";

    // $headers and $curl_cmd are used for error or debug
    $headers = '';
    if (isset($this->http_header_list[$this->http_header_type][$http_op])) {
      foreach ($this->http_header_list[$this->http_header_type][$http_op] as $header) {
        $H = trim($header);
        $headers .= " -H '{$H}'";
      }
    }

    $curl_cmd = "curl -X {$http_op} {$headers} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$url}'";

    if (count($cmd_list) > 2) {
      $rest_payload = $cmd_list[2];
      $curl_cmd .= " -d '{$rest_payload}'";
    } else {
      $rest_payload = '';
    }

    debug_dump($curl_cmd, "HTTP REQUEST:\n");
    $this->execute_curl_command($origin, $http_op, $url, $rest_payload, $curl_cmd);
    debug_dump($this->response, "HTTP RESPONSE:\n");
  }

  private function execute_curl_command($origin, $http_op, $url, $rest_payload, $curl_cmd) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    switch ($http_op) {
      case 'GET':
        break;
      case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        break;
      case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        break;
      case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "MSA");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->conn_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->conn_timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->http_header_list[$this->http_header_type][$http_op]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (!empty($rest_payload)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $rest_payload);
    }

    $ret = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch , CURLINFO_HEADER_SIZE);
    $header = substr($ret, 0, $header_size);
    $body = substr($ret, $header_size);

    curl_close($ch);

    if ($http_code < 200 || $http_code > 209) {
      $cmd_quote = str_replace("\"", "'", $body);
      $cmd_return = str_replace("\n", "", $cmd_quote);
      throw new SmsException("Call to API {$curl_cmd} Failed, header = $header, $cmd_return error", ERR_SD_CMDFAILED, $origin);
    }

    if (!empty($body)) {
      $result = preg_replace('/xmlns="[^"]+"/', '', $body);
      $result = preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $body);
      $array = json_decode ($result, true);

      if (isset($array['sid'])) {
        $this->key = $array['sid'];
      }
      $this->response = $array;
    }
    else
    {
      if ($http_code != 204) {
        throw new SmsException ("$origin: Repsonse to API {$curl_cmd} Failed, expected json received empty response, header $header", ERR_SD_CMDFAILED );
      }
    }
  }
}

class sdwan_connect extends connect {

  public function do_connect() {

    parent::do_connect();
    $cmd = 'GET#/sdwan/v2.1/api/profile';
    $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);
  }
}

// return false if error, true if ok
function connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
  global $sms_sd_ctx;
  global $model_data;

  $specific_data = json_decode($model_data, true);
  if (isset($specific_data['class'])) {
    $class = $specific_data['class'];
  } else {
    $class = 'connect';
  }
  $sms_sd_ctx = new $class($sd_ip_addr, $login, $passwd, $port_to_use);
  try
  {
    $sms_sd_ctx->do_connect();
  }
  catch (SmsException $e)
  {
    $sms_sd_ctx->disconnect();
    disconnect();
    throw new SmsException($e->getMessage(), $e->getCode());
  }

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function disconnect() {
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
