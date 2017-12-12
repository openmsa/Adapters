<?php
/*
 * Version: $Id$
 * Created: Jun 29, 2011
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// VMWARE HOST Asset management

require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';

require_once load_once('vmware', 'vmware_connect.php');
require_once load_once('vmware', 'common.php');


function exit_error($line, $error)
{
  sms_log_error("$line: $error\n");
  exit($error);
}

$net_profile = get_network_profile();
$sd = $net_profile->SD;
$hostServerIP = $sd->SD_IP_CONFIG;
$hostServerUser = $sd->SD_LOGIN_ENTRY;
$hostServerPassword = $sd->SD_PASSWD_ENTRY;

$hostCredentials = "--url https://$hostServerIP/sdk --username '$hostServerUser' --password '$hostServerPassword'";

$res = myShell_exec("/opt/VMwareAdaptor/bin/run.sh RetrieveAssetInformations $hostCredentials");

if (substr($res, 0, 1) !== "{") {
  exit_error(__LINE__ , $res);
  return 0;
}

$assetInfosArray = json_decode($res, true);
$asset['model'] = $assetInfosArray['Model'];
$hostsInfosArray = $assetInfosArray['Hosts'];
$asset_ex = array();

if ($assetInfosArray['apiType'] === 'HostAgent') {
  //it means the host is a direct host like an esx

  $currentHostInfos = reset($hostsInfosArray);//here, there is only one host in 'Hosts', so we take the first element of $hostsInfosArray
  $asset['cpu'] = $currentHostInfos['cpuName'];
  $memoryGB = $currentHostInfos['memorySize'] / pow(1024, 3);
  $asset['memory'] = round($memoryGB , 2).' GB';
  //TODO : $asset['firmware'] = 'TODO';
  fillOneHostAdditionalInfosArray($asset_ex , $currentHostInfos , null);
}
else if ($assetInfosArray['apiType'] === 'VirtualCenter') {
  //to check
  foreach ($hostsInfosArray as $hostName => $currentHostInfos) {
    fillOneHostAdditionalInfosArray($asset_ex , $currentHostInfos , $hostName);
  }
  addCpuCoresNumberInfos($asset_ex, $hostsInfosArray);
}

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

foreach ($asset_ex as $name => $value)
{
  $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
  if ($ret !== 0)
  {
    exit_error(__FILE__.':'.__LINE__, ": sms_sd_set_asset_attribute($name, $value) Failed\n");
  }
}

return SMS_OK;

?>