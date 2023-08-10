<?php
require_once 'smsd/generic_connection.php';

  // ---------------------------------------------------------------------------
  function get_health($actuator_protocol, $me_ip, $me_port, $actuator_base_url)
  {
    $url = "$actuator_protocol://$me_ip:$me_port/$actuator_base_url";
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
        'Accept:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $ret = curl_exec($ch);
    // sms_log_info(basename(__FILE__, '.php') . " polling: <$me_ip> with ret: $ret");

    if (curl_errno($ch))
    {
      sms_log_error("curl error: " . curl_error($ch) . "\n"
                    . "failed to get the actuator health!\n");
      return ERR_SD_NETWORK;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200)
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
$me_ip = $SD->SD_IP_CONFIG;

// default values
$actuator_protocol = 'http';
$actuator_port = 8100;
$actuator_base_url = 'nfvo-webapp/actuator/health';

if (!empty($SD->SD_CONFIGVAR_list['actuator_protocol']))
{
  $actuator_protocol = $SD->SD_CONFIGVAR_list['actuator_protocol']->VAR_VALUE;
}
if (!empty($SD->SD_CONFIGVAR_list['actuator_port']))
{
  $actuator_port = $SD->SD_CONFIGVAR_list['actuator_port']->VAR_VALUE;
}
if (!empty($SD->SD_CONFIGVAR_list['actuator_base_url']))
{
  $actuator_base_url = $SD->SD_CONFIGVAR_list['actuator_base_url']->VAR_VALUE;
}

$ret = get_health($actuator_protocol, $me_ip, $actuator_port, $actuator_base_url);

return $ret;
?>
