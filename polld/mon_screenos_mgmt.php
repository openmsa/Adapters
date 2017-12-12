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
$asset['model']    = substr($sysdescr, 0, strpos($sysdescr, ' '));
$start = strpos($sysdescr, '(SN: ') + strlen('(SN: ');
$end = strpos($sysdescr, ',', $start);
$asset['serial']   = substr($sysdescr, $start, $end);
$asset['firmware'] = getsnmp('1.3.6.1.4.1.3224.7.1.5.0', $sd_ip_addr, $community);
$asset['firmware'] = substr($asset['firmware'], 0, strpos($asset['firmware'], ' '));

if (empty($asset['model'])) { unset($asset['model']); }
if (empty($asset['firmware'])) { unset($asset['firmware']); }
if (empty($asset['serial'])) { unset($asset['serial']); }

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

return 0;

?>