<?php

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('cisco_ios_xr', 'cisco_ios_xr_connect.php');
require_once load_once('cisco_ios_xr', 'common.php');
require_once "$db_objects";

$specific_cmds = array(
    'SD_REBOOT' => 'func_reboot',
    'HSRP_STATUS' => 'func_hsrp_status'
);

function func_hsrp_status()
{
  global $sms_sd_ctx;
  global $result;

  $result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'show ip interface brief');

  $result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'show standby brief');
}


if (empty($smsexec_list))
{
  sms_send_user_ok($sms_csp, $sdid, '');
  return SMS_OK;
}

$network = get_network_profile();
$SD = &$network->SD;

$ret = cisco_ios_xr_connect();
if ($ret !== SMS_OK)
{
  sms_log_error(__FILE__.':'.__LINE__.": cisco_ios_xr_connect() failed\n");
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  return $ret;
}

$on_error_fct = 'cisco_disconnect';

$result = '';
$cmds = explode(',', $smsexec_list);
$insideConft = false;
$prompt = "lire dans sdctx";
foreach ($cmds as $cmd)
{
  if (!empty($specific_cmds[$cmd]))
  {
    // call specific function
    $specific_cmds[$cmd]();
  }
  else
  {
      if ($cmd == "conf t" || $insideConft )
      {
          $prompt = ")#";
          $insideConft = true;
      }
      if ($cmd == "exit" )
      {
          $prompt = "lire dans sdctx";
          $insideConft = false;
      }
    // send command
      $result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, $prompt, 36000, false);
  }
}

unset($on_error_fct);
cisco_ios_xr_disconnect();

sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;

?>