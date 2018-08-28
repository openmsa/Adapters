<?php
/*
 * Version: $Id: do_provisioning.php 105696 2016-02-03 16:38:12Z ydu $
 * Created: May 30, 2008
 * Available global variables
 *  $sms_sd_info        sd_info structure
 * 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_csp            pointer to csp context to retreive usefull field(s)
 *  $sdid
 *  $sms_module         module name (for patterns)
 */

// Initial provisioning

require_once 'polld/common.php';
require_once 'smsd/sms_common.php';

require_once load_once('mon_generic', 'provisioning_stages.php');
require_once "$db_objects";

function on_error_exit($log_msg, $error_id)
{
  global $sms_sd_info;
  global $sms_csp;
  global $sdid;
  global $stage;

  sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $error_id, null, '');
  sms_log_error($log_msg);
  exit($error_id);
}

// Reset the provisioning status in the database
// all the stages are marked "not run"
$ret = sms_bd_init_provstatus($sms_csp, $sms_sd_info, count($provisioning_stages), $provisioning_stages);
if ($ret)
{
  sms_send_user_error($sms_csp, $sdid, '', $ret);
  sms_close_user_socket($sms_csp);
  return $ret;
}
sms_send_user_ok($sms_csp, $sdid, '');
sms_close_user_socket($sms_csp);

$network = get_network_profile();
$SD = &$network->SD;

$poll_mode = POLL_PERMANENT;
$ext_int = &$SD->SD_INTERFACE_list['E'];
$sd_ip_addr = $ext_int->INT_IP_ADDR;

// In all cases unset lock provisioning (not in a stage)
$ret = sms_sd_prov_unlock($sms_csp, $sms_sd_info);
if ($ret != SMS_OK)
{
  on_error_exit(__FILE__.':'.__LINE__.": sms_sd_prov_unlock() returned $ret\n", $ret);
}

// -------------------------------------------------------------------------------------
// ICMP TEST
// -------------------------------------------------------------------------------------

$stage = 0;
$ret = sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'W', 0, null, ''); // working status
if ($ret)
{
  on_error_exit(__FILE__.':'.__LINE__.": sms_bd_set_provstatus() returned $ret\n", $ret, '');
}

$ret = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/icmp_test.sh -a $sd_ip_addr", $output);
if ($ret !== SMS_OK)
{
  sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', ERR_SD_NETWORK, 'W', implode($output));
}
else
{
  sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, 'W', '');
  $poll_mode |= POLL_PING;
}

if ($SD->SD_LOG)
{
  // -------------------------------------------------------------------------------------
  // SNMP TEST
  // -------------------------------------------------------------------------------------
  $stage += 1;

  $snmp_oid = "1.3.6.1.2.1.1.3.0"; // sysUpTime

  $cmd = "/opt/sms/bin/snmp_test.sh -t get -H $sd_ip_addr -o $snmp_oid";

  if (isset($SD->SD_CRUD_OBJECT_list['snmpv3.1.object_id']))
  {
    // SNMPv3
    $cmd .= ' -v 3';

    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_level']->CRUD_VALUE))
    {
      $sec_level = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_level']->CRUD_VALUE;
      $cmd .= " -l $sec_level";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_name']->CRUD_VALUE))
    {
      $sec_name = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_sec_name']->CRUD_VALUE;
      $cmd .= " -n $sec_name";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_type']->CRUD_VALUE))
    {
      $auth_type = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_type']->CRUD_VALUE;
      $cmd .= " -a $auth_type";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_phrase']->CRUD_VALUE))
    {
      $auth_phrase = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_auth_phrase']->CRUD_VALUE;
      $cmd .= " -A $auth_phrase";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_type']->CRUD_VALUE))
    {
      $priv_type = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_type']->CRUD_VALUE;
      $cmd .= " -x $priv_type";
    }
    if (!empty($SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_phrase']->CRUD_VALUE))
    {
      $priv_phrase = $SD->SD_CRUD_OBJECT_list['snmpv3.1.snmpv3_priv_phrase']->CRUD_VALUE;
      $cmd .= " -X $priv_phrase";
    }

    $ret = exec_local(__FILE__ . ':' . __LINE__, $cmd, $output);
    if ($ret !== SMS_OK)
    {
      sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', ERR_SD_SNMP, 'W', implode(' ', $output));
    }
  }
  else
  {
    $snmp_community = $SD->SD_SNMP_COMMUNITY;
    $cmd .= " -c $snmp_community";

    $ret = exec_local(__FILE__ . ':' . __LINE__, "$cmd -v 2c", $output);
    if ($ret !== SMS_OK)
    {
      // Try snmp v1
      $ret = exec_local(__FILE__ . ':' . __LINE__, "$cmd -v 1", $output);
      if ($ret !== SMS_OK)
      {
        sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', ERR_SD_SNMP, 'W', implode(' ', $output));
      }
      else
      {
        $poll_mode |= POLL_SNMP_V1;
      }
    }
  }

  if ($ret === SMS_OK)
  {
    $poll_mode |= POLL_ASSET;
    $poll_mode |= POLL_SNMP;
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, 'W', '');
  }

  $ret = sms_bd_set_poll_mode($sms_csp, $sms_sd_info, $poll_mode);
  if ($ret)
  {
    on_error_exit(__FILE__ . ':' . __LINE__ . ": sms_bd_set_poll_mode() returned $ret\n", $ret);
  }
}

// -------------------------------------------------------------------------------------
// DNS & IP CONFIG UPDATE
// -------------------------------------------------------------------------------------
$stage += 1;

// Set Ip config
$ret = sms_bd_set_ipconfig($sms_csp, $sms_sd_info, $sd_ip_addr);
if ($ret != SMS_OK)
{
  on_error_exit(__FILE__.':'.__LINE__.": sms_bd_set_ipconfig() returned $ret\n", $ret);
}

// DNS Update
$ret = dns_update($sdid, $sd_ip_addr);
if ($ret != SMS_OK)
{
  on_error_exit(__FILE__.':'.__LINE__.": dns_update() returned $ret\n", $ret);
}

sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, null, '');

sms_sd_forceasset($sms_csp, $sms_sd_info);

return 0;
?>
