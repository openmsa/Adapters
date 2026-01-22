<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class connect extends GenericConnection {

  private $uid;
  private $pswd;
  private $auth_url;
  private $login_url;
  private $cmd_url;
  private $auth_header;
  private $http_header_list;
  private $json_path;
  private $conn_timeout;
  private $response;
  private $cookie;
  private $session_id;

  public function __construct($ip = null, $login = null, $passwd = null, $admin_password = null, $port = null)
  {
    $network = get_network_profile();
    $sd = &$network->SD;

    $this->sd_ip_config = empty($ip) ? $sd->SD_IP_CONFIG : $ip;
    $this->sd_login_entry = empty($login) ? $sd->SD_LOGIN_ENTRY : $login;
    $this->uid = rawurlencode(base64_encode($this->sd_login_entry));
    $this->sd_passwd_entry = empty($passwd) ? $sd->SD_PASSWD_ENTRY : $passwd;
    $this->pswd = rawurlencode(base64_encode($this->sd_passwd_entry));
    $this->auth_url = 'auth/admin.html';
    $this->login_url = 'api/auth/login';
    $this->cmd_url = 'api/commands';
    $this->auth_header = 'User-Agent: opslab';
    $this->http_header_list = [$this->auth_header];
    $this->json_path = new \JsonPath\JsonPath();
    $this->conn_timeout = 10;
    $this->response = null;
    $this->cookie = null;
    $this->session_id = null;
  }

  public function do_connect() {

    // Get cookie
    unset($this->cookie);
    $rawdata = "app=sslclient&uid={$this->uid}&pswd={$this->pswd}&totp=";
    $cmd = "POST#{$this->auth_url}#{$rawdata}";
    $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);

    $this->http_header_list[] = "Cookie: {$this->cookie}";

    // Get session id
    unset($this->session_id);
    $cmd = "POST#{$this->login_url}#{$rawdata}";
    $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);
    $this->session_id = (string)$this->response->sessionid;
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

    $url = "https://{$this->sd_ip_config}/{$rest_path}";

    $headers = '';
    foreach ($this->http_header_list as $header) {
      $H = trim($header);
      $headers .= " -H '{$H}'";
    }

    // for debug
    $curl_cmd = "curl -X {$http_op} {$headers} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$url}'";

    if (count($cmd_list) > 2) {
      if (isset($this->session_id)) {
        $payload = $cmd_list[2];
        if (strpos($rest_path, 'commands') !== false) {
          // Multiline payload
          $cmds = explode("\n", $payload);
          $i = 1;
          $rest_payload = '';
          foreach($cmds as $cmd) {
            $cmd_encoded = rawurlencode(trim($cmd));
            if ($i > 1) {
              $rest_payload .= "&cmd$i=$cmd_encoded";
            } else {
              $rest_payload .= "cmd$i=$cmd_encoded";
            }
            $i++;
          }
        } else {
          // One line payload
          $cmd = rawurlencode($payload);
          $rest_payload = "cmd=$cmd";
        }
        $rest_payload .= "&sessionid={$this->session_id}";
      } else {
        // Keep payload as it is
        $rest_payload = $cmd_list[2];
      }
    } else {
      $rest_payload = '';
    }

    $curl_cmd .= " --data-raw '{$rest_payload}'";

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->http_header_list);
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

    if (!isset($this->cookie)) {
      $http_headers = http_parse_headers($header);
      $this->cookie = $http_headers['Set-Cookie'];
    }

    if (!empty($body)) {
      $result = preg_replace('/xmlns="[^"]+"/', '', $body);
      $this->response = new SimpleXMLElement($result);
    }
    else
    {
      if ($http_code != 204) {
        throw new SmsException ("$origin: Repsonse to API {$curl_cmd} Failed, expected json received empty response, header $header", ERR_SD_CMDFAILED );
      }
    }
  }
}


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

function disconnect() {
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
