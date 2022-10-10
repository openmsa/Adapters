<?php

require_once 'smsd/sms_common.php';

require_once "$db_objects";

$net = get_network_profile();
$sd = $net->SD;

if (empty($sd->SD_CONFIGVAR_list) || empty($sd->SD_CONFIGVAR_list['cloudService'])) {
  sms_log_error("configuration variable cloudService not configured\n");
  return ERR_CONFIG_VAR_UNDEFINED;
}

switch ($sd->SD_CONFIGVAR_list['cloudService']->VAR_VALUE) {
  case 'aks':
    if (!empty($sd->SD_CONFIGVAR_list['host'])) {
      $end_point = $sd->SD_CONFIGVAR_list['host']->VAR_VALUE;
    }
    break;
  case 'eks':
    if (!empty($sd->SD_CONFIGVAR_list['cluster_endpoint'])) {
      $end_point = $sd->SD_CONFIGVAR_list['cluster_endpoint']->VAR_VALUE;
    }
    break;
  case 'gke':
    if (!empty($sd->SD_CONFIGVAR_list['cluster_endpoint'])) {
      $end_point = $sd->SD_CONFIGVAR_list['cluster_endpoint']->VAR_VALUE;
    }
    break;
  default:
    sms_log_error("Unknown cloudService\n");
    return ERR_CONFIG_VAR_UNDEFINED;
}

if (empty($end_point)) {
  sms_log_error("No cluster endpoint\n");
  return ERR_CONFIG_VAR_UNDEFINED;
}

if (strpos($end_point, 'http') === false) {
  // Assume $end_point is an IP or fqdn
  $cmd = "ping -q -W 5 -c 1 $end_point";
  $ret = exec_local(__FILE__.':'.__LINE__, $cmd, $output);
  if ($ret != SMS_OK) {
    sms_log_error("ping failed, $end_point\n");
    return ERR_SD_NETWORK;
  }
} else {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $end_point);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $ret = curl_exec($ch);
  curl_close($ch);
  if ($ret === false) {
    sms_log_error("curl failed, $end_point\n");
    return ERR_SD_NETWORK;
  }

  $array_ret = json_decode($ret);
  if (empty($array_ret)) {
    sms_log_error("curl failed, $end_point, empty data\n");
    return ERR_SD_NETWORK;
  }

  // whatever the response of the kubernetes cluster, assume it is running
}

return SMS_OK;

?>