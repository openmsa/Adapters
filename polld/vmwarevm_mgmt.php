<?php
/*
 * Version: $Id$
* Created: Jun 29, 2011
* Available global variables
*  $sdid
* 	$SMS_RETURN_BUF    string buffer containing the result
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
$host = &$net_profile->VM_HOST;
$hostServerIP = $host->SD_IP_CONFIG;
$hostServerUser = $host->SD_LOGIN_ENTRY;
$hostServerPassword = $host->SD_PASSWD_ENTRY;

$sd = &$net_profile->SD;
$vmName = $sd->SD_CRUD_OBJECT_list['basic.0.vmx'];



$hostCredentials = "--url https://$hostServerIP/sdk --username '$hostServerUser' --password '$hostServerPassword'";

$res = myShell_exec("/opt/VMwareAdaptor/bin/run.sh ListAllVMCharacteristics $hostCredentials");

if (substr($res, 0, 1) !== "{") {
  exit_error(__LINE__ , $res);
  return 0;
}

$vmsInfoInfosArray = json_decode($res, true);
$currentVmInfos = $vmsInfoInfosArray[$vmName];

$cpu = $currentVmInfos['numvcpus'];
$mem = $currentVmInfos['memsize']/1024;

$asset['cpu'] = $cpu;
$asset['memory'] = round($mem , 2).' GB';

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
	exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}


//block for additional asset informations, useless for the moment :
/*foreach ($asset_ex as $name => $value)
{
  $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
  if ($ret !== 0)
  {
		exit_error(__FILE__.':'.__LINE__, ": sms_sd_set_asset_attribute($name, $value) Failed\n");
  }
}
*/
return SMS_OK;

?>