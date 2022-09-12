<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';

require_once "$db_objects";

class MeConnection extends GenericConnection {

  protected $response; // either SimpleXMLElement or array, depending of rest_json
  protected $header;
  public $rest_json;
  public $json_path;
  public $http_header_list = array(
      'GET' => array('Accept: application/json'),
      'POST' => array('Content-Type: application/json', 'Accept: application/json'),
      'PUT' => array('Content-Type: application/json', 'Accept: application/json'),
      'DELETE' => array('Accept: application/json'),
  );
  public $protocol = 'https';
  public $conn_timeout = EXPECT_DELAY / 1000;
  public $key;

  public function do_connect() {

    $network = get_network_profile();
    $sd = &$network->SD;

    if (isset($sd->SD_CONFIGVAR_list['REST_JSON'])) {
      $this->rest_json=trim($sd->SD_CONFIGVAR_list['REST_JSON']->VAR_VALUE);
      $this->json_path = new \JsonPath\JsonPath();
    }

    // Authentication part
    // https://www.juniper.net/documentation/en_US/contrail20/information-products/pathway-pages/api-guide-2011/tutorial_with_rest.html#authentication
    // https://docs.openstack.org/api-quick-start/api-quick-start.html

    $auth_array = array();
    $auth_array['auth'] = array();
    $auth_array['auth']['identity'] = array();
    $auth_array['auth']['identity']['methods'] = array();
    $methods = array('password');
    $auth_array['auth']['identity']['methods'] = $methods;
    $auth_array['auth']['identity']['password'] = array();
    $auth_array['auth']['identity']['password']['user'] = array();
    $auth_array['auth']['identity']['password']['user']['domain'] = array();

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_PROTOCOL'])) {
      $keystone_proto = trim($sd->SD_CONFIGVAR_list['KEYSTONE_PROTOCOL']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_IP'])) {
      $keystone_ip = trim($sd->SD_CONFIGVAR_list['KEYSTONE_IP']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_PORT'])) {
      $keystone_port = trim($sd->SD_CONFIGVAR_list['KEYSTONE_PORT']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_USER_DOMAIN_NAME'])) {
      $auth_array['auth']['identity']['password']['user']['domain']['name'] = trim($sd->SD_CONFIGVAR_list['KEYSTONE_USER_DOMAIN_NAME']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_USER_NAME'])) {
      $auth_array['auth']['identity']['password']['user']['name'] = trim($sd->SD_CONFIGVAR_list['KEYSTONE_USER_NAME']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_USER_PASSWORD'])) {
      $auth_array['auth']['identity']['password']['user']['password'] = trim($sd->SD_CONFIGVAR_list['KEYSTONE_USER_PASSWORD']->VAR_VALUE);
    }

    if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_DOMAIN_NAME']) || isset($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_NAME'])) {
      $auth_array['auth']['scope'] = array();
      $auth_array['auth']['scope']['project'] = array();
      if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_DOMAIN_NAME'])) {
        $auth_array['auth']['scope']['project']['domain'] = array();
        $auth_array['auth']['scope']['project']['domain']['name'] = trim($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_DOMAIN_NAME']->VALUE);
      }
      if (isset($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_NAME'])) {
        $auth_array['auth']['scope']['project']['name'] = trim($sd->SD_CONFIGVAR_list['KEYSTONE_PROJECT_NAME']->VALUE);
      }
    }

    $keystone_token_req = '/v3/auth/tokens?nocatalog'; // Config var ?

    // WARNING : Do not call $this->send() because it uses credentials of the Contrail
    // while we have to use the ones of the Keystone
    $url = "{$keystone_proto}://{$keystone_ip}:{$keystone_port}{$keystone_token_req}";

    $payload = json_encode($auth_array);

    $this->execute_curl_command(__FILE__ . ':' . __LINE__, 'do_connect', 'POST', $url, $payload);

    if (preg_match('/X-Subject-Token:\s+(.*)$/m', $this->header, $matches) != 1) {
      throw new SmsException("Authentication failed, no token in header response to $url, header : $this->header", ERR_SD_AUTH, __FILE__ . ':' . __LINE__);
    }

    $this->key = trim($matches[1]);
  }

  public function do_disconnect() {
    // Revoke token ?
  }

  public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true) {

    $this->send ( $origin, $cmd );

    if ($prompt !== 'lire dans sdctx' && ! empty ( $prompt )) {
      $tab [0] = $prompt;
    } else {
      $tab = array ();
    }

    $this->expect ( $origin, $tab );

    if (!$this->rest_json && is_array($this->last_result)) {
      return $this->last_result[0];
    }
    return $this->last_result;
  }

  public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result') {
    global $$global_result_name;

    if (! isset ( $this->response )) {
      throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
    }
    $index = 0;
    if (empty ( $tab )) {
      $$global_result_name = $this->response;
      $this->last_result = $this->response;
      return $index;
    }
    foreach ( $tab as $path ) {
      if ($this->rest_json) {
        $result = $this->json_path->find($this->response, $path);
      } else {
        $result = $this->response->xpath ( $path );
      }
      if (($result !== false) && ! empty ( $result )) {
        $$global_result_name = $result;
        $this->last_result = $result;
        return $index;
      }
      $index ++;
    }

    throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
  }

  public function send($origin, $rest_cmd) {
    unset ( $this->response );
    $cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);

    $http_op = $cmd_list[0];

    if (isset($this->key)) {
      $this->http_header_list[$http_op][] = "X-Auth-Token: {$this->key}";
    }

    if (count($cmd_list) > 1 ) {
      $path = $cmd_list[1];
    } else {
      $path = '';
    }

    $url = "{$this->protocol}://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";

    if (count($cmd_list) > 2 ) {
      $payload = $cmd_list[2];
    } else {
      $payload = null;
    }

    $this->execute_curl_command ($origin, $rest_cmd, $http_op, $url, $payload);
  }

  protected function execute_curl_command($origin, $rest_cmd, $http_op, $url, $payload) {

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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->conn_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->conn_timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->http_header_list[$http_op]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (!empty($payload)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $ret = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch , CURLINFO_HEADER_SIZE);
    $header = substr($ret, 0, $header_size);
    $body = substr($ret, $header_size);

    curl_close($ch);

    if ($http_code < 200 || $http_code > 209) {
      $cmd_quote = str_replace ( "\"", "'", $body );
      $cmd_return = str_replace ( "\n", "", $cmd_quote );
      throw new SmsException("Call to API {$rest_cmd} Failed = $header, $cmd_return error", ERR_SD_CMDFAILED, $origin);
    }

    if (!empty($body)) {
      $result = preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $body);
      $array = json_decode ($result, true);

      if ($this->rest_json) {
        $response = $array;
      } else {
        // call array to xml conversion function
        $response = arrayToXml ($array, '<root></root>');
      }
    }
    else
    {
      if ($this->rest_json) {
        throw new SmsException ("$origin: Repsonse to API {$rest_cmd} Failed, expected json received empty response, header $header", ERR_SD_CMDFAILED );
      }
      $response = new SimpleXMLElement('<root></root>');
    }

    $this->header = $header;
    $this->response = $response;

    debug_dump(($this->rest_json) ? $this->response : $this->response->asXML(), "DEVICE RESPONSE\n");
  }
}

// return false if error, true if ok
function me_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
  global $sms_sd_ctx;
  global $SMS_OUTPUT_BUF;

  try
  {
    $sms_sd_ctx = new MeConnection( $sd_ip_addr, $login, $passwd, null, $port_to_use );
  }
  catch (SmsException $e)
  {
    me_disconnect();
    $SMS_OUTPUT_BUF = $e->getMessage();
    return $e->getCode();
  }

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function me_disconnect() {
  global $sms_sd_ctx;

  if (isset($sms_sd_ctx))
  {
    $sms_sd_ctx->disconnect();
    $sms_sd_ctx = null;
  }
  return SMS_OK;
}

?>
