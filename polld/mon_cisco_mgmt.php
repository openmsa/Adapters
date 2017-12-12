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

require_once 'polld/snmp.php';

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

$sysdescr = getsnmp('1.3.6.1.2.1.1.1.0', $sd_ip_addr, $community);

/* example
Cisco IOS Software, C837 Software (C837-K9O3SY6-M), Version 12.4(6)T9, RELEASE SOFTWARE (fc2)
Technical Support: http://www.cisco.com/techsupport
Copyright (c) 1986-2007 by Cisco Systems, Inc.
Compiled Tue 09-Oct-07 18:45 by khuie
 */

$firmware = $sysdescr;
$start = strpos($firmware, ', Version ') + strlen(', Version ');
$end = strpos($firmware, ', RELEASE SOFTWARE', $start);
$asset['firmware'] = substr($firmware, $start, $stop);

$license = $sysdescr;
$start = strpos($license, ' Software (') + strlen(' Software (');
$end = strpos($license, '), Version', $start);
$asset['license'] = substr($license, $start, $stop);

$asset['model']  = 'Cisco '.getsnmp('1.3.6.1.4.1.9.3.6.11.1.3.1', $sd_ip_addr, $community);

$serial = getsnmp('1.3.6.1.4.1.9.3.6.3.0', $sd_ip_addr, $community);
$asset['serial'] = substr($serial, 0, strpos($serial, ' '));

if (empty($asset['model'])) { unset($asset['model']); }
if (empty($asset['firmware'])) { unset($asset['firmware']); }
if (empty($asset['license'])) { unset($asset['license']); }
if (empty($asset['serial'])) { unset($asset['serial']); }
//if (empty($asset['memory'])) { unset($asset['memory']); }

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

return 0;

?>