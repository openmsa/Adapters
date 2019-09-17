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

$asset['model']    = getsnmp('1.3.6.1.2.1.47.1.1.1.1.13.1', $sd_ip_addr, $community);
$asset['serial']   = getsnmp('1.3.6.1.2.1.47.1.1.1.1.11.1', $sd_ip_addr, $community);
$asset['firmware'] = getsnmp('1.3.6.1.2.1.47.1.1.1.1.10.1', $sd_ip_addr, $community);
$asset['memory']   = floor((getsnmp('1.3.6.1.4.1.9.9.48.1.1.1.5.1', $sd_ip_addr, $community) + getsnmp('1.3.6.1.4.1.9.9.48.1.1.1.6.1', $sd_ip_addr, $community)) / (1024*1024)) . ' MB';

if (empty($asset['model'])) { unset($asset['model']); }
if (empty($asset['firmware'])) { unset($asset['firmware']); }
if (empty($asset['serial'])) { unset($asset['serial']); }
if (empty($asset['memory'])) { unset($asset['memory']); }

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

return 0;

?>