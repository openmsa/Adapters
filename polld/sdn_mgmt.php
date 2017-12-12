<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management

require_once 'polld/common.php';
require_once 'polld/snmp.php';
require_once 'smsd/sms_common.php';

require_once "$db_objects";


function exit_error($line, $error)
{
  sms_log_error("$line: $error\n");
  exit($error);
}

$network = get_network_profile();
$SD = &$network->SD;
$sd_ip_addr = $SD->SD_IP_CONFIG;
$community  = $SD->SD_SNMP_COMMUNITY;
$poll_mode  = $SD->SD_POLL_MODE;


if (($poll_mode & POLL_SNMP_V1) !== 0)
{
  $version = '1';
}
else
{
  $version = '2c';
}

//===================================================
// ASSET GENERIC
//===================================================
$sysdescr = getsnmp('sysDescr.0', $sd_ip_addr, $community, $version);
$asset['model'] = substr($sysdescr, 0, strpos($sysdescr,'#'));
if (empty($asset['model']))
{
  unset($asset['model']);
}
$asset['serial'] = substr($sysdescr,strpos($sysdescr,'#')+1);
if (empty($asset['serial']))
{
	unset($asset['serial']);
}
$asset['memory'] = getsnmp('1.3.6.1.4.1.2021.4.5.0', $sd_ip_addr, $community, $version);
if (empty($asset['memory']))
{
  unset($asset['memory']);
}
$NumberOfCpu = getsnmp('hrProcessorFrwID', $sd_ip_addr, $community, $version);
//[root@AAA sms]# snmpwalk -v1 -c ubiqube 10.30.18.159 hrProcessorFrwID
//HOST-RESOURCES-MIB::hrProcessorFrwID.768 = OID: SNMPv2-SMI::zeroDotZero
//HOST-RESOURCES-MIB::hrProcessorFrwID.769 = OID: SNMPv2-SMI::zeroDotZero
//TODO replace 768 by extract from previous var
$asset['cpu'] = getsnmp('hrDeviceDescr.768', $sd_ip_addr, $community, $version);
if (empty($asset['cpu']))
{
  unset($asset['cpu']);
}



//===================================================
// ASSET FIRMWARE OPENDAYLIGHT
//===================================================
$asset['firmware'] = 'Hydrogen 	1.0';
//get value by cURL
$delay = 50;
$cmdODLbundles="curl -u admin:admin --connect-timeout {$delay} --max-time {$delay} -H 'Accept: application/json' 'http://10.30.18.159:8080/controller/osgi/system/console/bundles'";
preg_match_all('@"name":"(?<name>[^"]+)([\w\s",:.]+)version":"(?<version>[^"]+)@',
shell_exec($cmdODLbundles),
$out, PREG_PATTERN_ORDER);
echo $out['name'][0]. ", " .$out['version'][0]."\n";
for ($i = 0; $i < count($out['name']); $i++) {
	$varname=$out['name'][$i];
	$varversion=$out['version'][$i];
	$ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $varname, $varversion);
	if ($ret !== 0)
	{
		exit_error(__FILE__.':'.__LINE__, ": sms_sd_set_asset_attribute($varname, $varversion) Failed\n");
	}
	$asset_attributes[$out['name'][$i]] = $out['name'][$i];

	$asset[$varname]=$varversion;
	//echo $out['name'][$i]. "->" .$out['version'][$i]."\n";
}

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

return 0;

?>