<?php
require_once 'smsd/generic_connection.php';

// Same script for nfvo and nfvm, differ only with following var:
// for nfvo: 'vnfpkgm/api_versions'; for vnfm: 'vnflcm/api_versions'
$health_path = 'vnflcm/api_versions';

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
  function get_token($SIGNIN_REQ_PATH, $data)
  {
    $http_data = http_build_query($data);

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

    $ret = curl_exec($ch);
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

    // print_r($ret); echo "\n";
    curl_close($ch);

    return curl_json_output($ret)->access_token;
  }

  // ---------------------------------------------------------------------------
  function get_health($PROTOCOL, $ME_IP, $HTTP_PORT, $BASE_URL_MS, $TOKEN)
  {
    global $health_path;

    //$url = "$PROTOCOL://$ME_IP:$HTTP_PORT/$BASE_URL_MS/$health_path";
     $url = "$PROTOCOL://$ME_IP:$HTTP_PORT";
    if (!(empty($BASE_URL_MS)) && ($BASE_URL_MS !== '/')){
        $url .= "/$BASE_URL_MS";
    }
    $url .= "/$health_path";

    // sms_log_info(basename(__FILE__, '.php') . " polling: $url");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept:application/json',
        "Authorization: Bearer $TOKEN"));
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
$PROTOCOL        = $SD->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE;
$HTTP_PORT       = $SD->SD_CONFIGVAR_list['HTTP_PORT']->VAR_VALUE;
$BASE_URL_MS     = trim($SD->SD_CONFIGVAR_list['BASE_URL_MS']->VAR_VALUE, '/');
$SIGNIN_REQ_PATH = $SD->SD_CONFIGVAR_list['SIGNIN_REQ_PATH']->VAR_VALUE;

// we get the bearer token
$credentials = array('grant_type' => 'client_credentials',
                     'client_id'  => $ME_USER_NAME,
                     'client_secret' => $ME_PASSWORD);

$token = get_token($SIGNIN_REQ_PATH, $credentials);
// sms_log_info(basename(__FILE__, '.php') . " polling token: $token");

$ret = get_health($PROTOCOL, $ME_IP, $HTTP_PORT, $BASE_URL_MS, $token);

return $ret;
?>
