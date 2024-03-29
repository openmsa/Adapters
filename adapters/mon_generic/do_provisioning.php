<?php

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

  $cmd_v3 = '';

  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_securityLevel']))
  {
    $sec_level = $SD->SD_CONFIGVAR_list['snmpv3_securityLevel']->VAR_VALUE;
    $cmd_v3 .= " -l $sec_level";
  }
  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_securityName']))
  {
    $sec_name = $SD->SD_CONFIGVAR_list['snmpv3_securityName']->VAR_VALUE;
    $cmd_v3 .= " -n $sec_name";
  }
  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_authProtocol']))
  {
    $auth_type = $SD->SD_CONFIGVAR_list['snmpv3_authProtocol']->VAR_VALUE;
    $cmd_v3 .= " -a $auth_type";
  }
  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_authKey']))
  {
    $auth_phrase = $SD->SD_CONFIGVAR_list['snmpv3_authKey']->VAR_VALUE;
    $cmd_v3 .= " -A $auth_phrase";
  }
  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_privProtocol']))
  {
    $priv_type = $SD->SD_CONFIGVAR_list['snmpv3_privProtocol']->VAR_VALUE;
    $cmd_v3 .= " -x $priv_type";
  }
  if (!empty($SD->SD_CONFIGVAR_list['snmpv3_privKey']))
  {
    $priv_phrase = $SD->SD_CONFIGVAR_list['snmpv3_privKey']->VAR_VALUE;
    $cmd_v3 .= " -X $priv_phrase";
  }

  if (!empty($cmd_v3))
  {
    // SNMPv3
    $cmd .= ' -v 3' . $cmd_v3;

    $ret = exec_local(__FILE__ . ':' . __LINE__, $cmd, $output);
    if ($ret !== SMS_OK)
    {
      sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', ERR_SD_SNMP, 'W', "$cmd => " . implode(' ', $output));
    }
  }
  else
  {
    // SNMPv2
    $snmp_community = $SD->SD_SNMP_COMMUNITY;
    $cmd .= " -v 2c -c $snmp_community";

    $ret = exec_local(__FILE__ . ':' . __LINE__, "$cmd -v 2c", $output);
    if ($ret !== SMS_OK)
    {
      // Try SNMPv1
      $ret = exec_local(__FILE__ . ':' . __LINE__, "$cmd -v 1", $output);
      if ($ret !== SMS_OK)
      {
        sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', ERR_SD_SNMP, 'W', "$cmd -v 1 => " . implode(' ', $output));
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
// IP CONFIG UPDATE
// -------------------------------------------------------------------------------------
$stage += 1;

// Set Ip config
$ret = sms_bd_set_ipconfig($sms_csp, $sms_sd_info, $sd_ip_addr);
if ($ret != SMS_OK)
{
  on_error_exit(__FILE__.':'.__LINE__.": sms_bd_set_ipconfig() returned $ret\n", $ret);
}

sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, null, '');

sms_sd_forceasset($sms_csp, $sms_sd_info);

return 0;
?>

