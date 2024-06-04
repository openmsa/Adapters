<?php
require_once 'smsd/generic_connection.php';

// Same script for nfvo and nfvm, differ only with following var:
// for nfvo: 'vnfpkgm/api_versions'; for vnfm: 'vnflcm/api_versions'
$health_path = 'vnflcm/api_versions';
//----------------------------------------------
function getHeaders($respHeaders) {
    $headers = array();

    $headerText = substr($respHeaders, 0, strpos($respHeaders, "\r\n\r\n"));
echo "getting header text: $headerText\n";
    foreach (explode("\r\n", $headerText) as $i => $line) {
        if ($i === 0) {
            $headers['http_code'] = $line;
        } else {
		echo "naveen--$line\n";
            list ($key, $value) = explode(': ', $line);

            $headers[$key] = $value;
        }
    }

    return $headers;
}



  // ---------------------------------------------------------------------------
  function curl_json_output($raw_output)
  {
    $json_ret = json_decode($raw_output);
    if (json_last_error() !== JSON_ERROR_NONE)
    {
      echo json_last_error_msg(), "\n";
      reset_alarm_flag_and_quit();
    }

    if (isset($json_ret->errorCode))
    {
      if (isset($json_ret->message))
      {
        // can be: 'ERROR: relation "redone.alarm" does not exist'
        echo $json_ret->message, "\n";
        reset_alarm_flag_and_quit();
      }
      else
      {
        echo "ERROR, curl command failed.\n";
        reset_alarm_flag_and_quit();
      }
    }
    return $json_ret;
  }

  // ---------------------------------------------------------------------------
  function get_token($SIGNIN_REQ_PATH, $data, $auth_mode)
  {
    debug_dump($auth_mode, "get_token() auth_mode\n");
    debug_dump($data, "get_token() data\n");
    $http_data ="";
    if($auth_mode == 'oauth_v2'){
      $http_data = http_build_query($data);
    }else if($auth_mode =='keystone'){
      $http_data = $data;
    }

    debug_dump($http_data, "get_token() http_data\n");


    $url = $SIGNIN_REQ_PATH;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $http_data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if($auth_mode == 'keystone'){
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'));
    }
    
    $ret = curl_exec($ch);
    debug_dump($ret, "get_token() curl return\n");
    // sms_log_info(basename(__FILE__, '.php') . " polling $url with get_token() ret: $ret");
    if (curl_errno($ch))
    {
      echo "curl error: ", curl_error($ch), "\n";
      echo "failed to get a Bearer token!\n";
      return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 300)
    {
      echo "$url failed with http code $http_code\n";
      print_r($ret);
      echo "\n";
      return false;
    }

    if($auth_mode == 'oauth_v2'){
      curl_close($ch);
      return curl_json_output($ret)->access_token;
    }else if($auth_mode == 'keystone'){
echo "===================================\n";
      curl_close($ch);
$nav=url_json_output($ret)->X-Subject-Token;
echo "papin: $nav \n";
      return $tok;
    }

  }

  // ---------------------------------------------------------------------------
  function get_health($PROTOCOL, $ME_IP, $HTTP_PORT, $BASE_URL_MS, $TOKEN, $auth_mode)
  {
    global $health_path;

    $url = "$PROTOCOL://";
    if (! isset($BASE_URL_MS)) $BASE_URL_MS = '/';
    $url .= preg_replace("!/+!", "/", "$ME_IP:$HTTP_PORT/$BASE_URL_MS/$health_path");
    // sms_log_info(basename(__FILE__, '.php') . " polling: $url");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    if($auth_mode =='oauth_v2'){
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept:application/json',
        "Authorization: Bearer $TOKEN"));
    }else if($auth_mode == 'keystone'){
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json', 'Accept:application/json',
        "X-Auth-Token: $TOKEN"));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $ret = curl_exec($ch);
    // sms_log_info(basename(__FILE__, '.php') . " polling: <$ME_IP> with ret: $ret");

    if (curl_errno($ch))
    {
      sms_log_error("curl error: " . curl_error($ch) . "\n"
                    . "failed to get the health!\n");
      return ERR_SD_NETWORK;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 300)
    {
      sms_log_error("$url failed with http code $http_code\n" . print_r($ret, true) . "\n");
      return ERR_SD_FAILED;
    }

    curl_close($ch);
    return SMS_OK;
  }
  // ---------------------------------------------------------------------------


$PROTOCOL="";
$HTTP_PORT="";
$BASE_URL_MS="";
$SIGNIN_REQ_PATH=""; 
$AUTH_TYPE="";
$tenant_id="";
$user_domain_id="";
$project_domain_id="";

$network = get_network_profile();
$SD = &$network->SD;

// the vars from SD
$ME_IP        = $SD->SD_IP_CONFIG;
$ME_USER_NAME = $SD->SD_LOGIN_ENTRY;
$ME_PASSWORD  = $SD->SD_PASSWD_ENTRY;

// the configuration vars
$missing_conf_vars = array();
$needed_conf_vars = array('PROTOCOL', 'HTTP_PORT', 'BASE_URL_MS', 'SIGNIN_REQ_PATH');

// we check the needed config vars
foreach ($needed_conf_vars as $conf_var_name)
{
  if (empty($SD->SD_CONFIGVAR_list[$conf_var_name]))
  {
    $missing_conf_vars[] = $conf_var_name;
  }
}

if (!empty($missing_conf_vars))
{
  sms_log_error("Error: missing config vars for polling: " . implode(', ', $missing_conf_vars));
  return ERR_SD_NOT_CONFIGURED;
}

if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
  $PROTOCOL        = $SD->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['HTTP_PORT'])) {
  $HTTP_PORT       = $SD->SD_CONFIGVAR_list['HTTP_PORT']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['BASE_URL_MS'])) {
  $BASE_URL_MS     = trim($SD->SD_CONFIGVAR_list['BASE_URL_MS']->VAR_VALUE, '/');
}
if (isset($sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH'])) {
  $SIGNIN_REQ_PATH = $SD->SD_CONFIGVAR_list['SIGNIN_REQ_PATH']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['AUTH_MODE'])) {
  $AUTH_TYPE = $SD->SD_CONFIGVAR_list['AUTH_MODE']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['TENANT_ID'])) {
  $tenant_id = $SD->SD_CONFIGVAR_list['TENANT_ID']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['USER_DOMAIN_ID'])) {
  $user_domain_id = $SD->SD_CONFIGVAR_list['USER_DOMAIN_ID']->VAR_VALUE;
}
if (isset($sd->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID'])) {
  $project_domain_id = $SD->SD_CONFIGVAR_list['PROJECT_DOMAIN_ID']->VAR_VALUE;
}

// we get the bearer token
$credentials = array('grant_type' => 'client_credentials',
                     'client_id'  => $ME_USER_NAME,
                     'client_secret' => $ME_PASSWORD);
if($AUTH_TYPE == 'keystone'){
	$credentials = "{\"auth\": {\"identity\": {\"methods\": [\"password\"], \"password\": {\"user\": {\"domain\": {\"name\":";
    $credentials .= "\"{$user_domain_id}\"},\"name\": \"{$ME_USER_NAME}\",\"password\": \"{$ME_PASSWORD}\"}}},";
    $credentials .= "\"scope\": {\"project\": {\"domain\": {\"name\": \"{$project_domain_id}\"}, \"id\": \"{$tenant_id}\"}}}}";
}

$token = get_token($SIGNIN_REQ_PATH, $credentials, $AUTH_TYPE);
// sms_log_info(basename(__FILE__, '.php') . " polling token: $token");
echo("token : ".$token."\n");

$ret = get_health($PROTOCOL, $ME_IP, $HTTP_PORT, $BASE_URL_MS, $token, $AUTH_TYPE);

return $ret;
?>
