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
$cmd ='';
if (isset($SD->SD_CRUD_OBJECT_list['snmpv3.1.object_id'])){
    $version = '3';
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_level']))
    {
      $sec_level = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_level'];
      $cmd .= " -l $sec_level";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_name']))
    {
      $sec_name = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_name'];
      $cmd .= " -n $sec_name";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_type']))
    {
      $auth_type = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_type'];
      $cmd .= " -a $auth_type";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_phrase']))
    {
      $auth_phrase = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_phrase'];
      $cmd .= " -A $auth_phrase";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_type']))
    {
      $priv_type = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_type'];
      $cmd .= " -x $priv_type";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_phrase']))
    {
      $priv_phrase = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_phrase'];
      $cmd .= " -X $priv_phrase";
    }	

}
$asset['model'] = getsnmp('1.3.6.1.2.1.1.1.0', $sd_ip_addr, $community, $version, $cmd);
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
