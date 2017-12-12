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
$asset['model'] = getsnmp('1.3.6.1.2.1.1.1.0', $sd_ip_addr, $community, $version);
if (empty($asset['model']))
{
  unset($asset['model']);
}

$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
if ($ret !== 0)
{
  exit_error(__FILE__.':'.__LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
}

return 0;

?>