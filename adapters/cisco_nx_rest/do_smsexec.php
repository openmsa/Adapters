<?php
/*
 * Version: $Id$
 * Created: Dec 3, 2009
 * Available global variables
 *  $sdid              ID of the SD
 *  $sms_csp           pointer to csp context to send response to user
 *  $sms_module        module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 *  $db_objects        script containing objects extracted from database
 */

// Enter Script description here

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once ( 'cisco_nx_rest', 'common.php' );
require_once load_once ( 'cisco_nx_rest', 'me_connect.php' );
require_once load_once ( 'cisco_nx_rest', 'apply_errors.php' );

require_once "$db_objects";

$specific_cmds = array(
    'SD_REBOOT' => 'func_reboot',
    'HSRP_STATUS' => 'func_hsrp_status'
);

global $apply_errors;
global $sms_sd_ctx;
      
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

#$ret = me_connect();
$ret = me_cli_connect();

if ($ret != SMS_OK)
  {
  #throw new SmsException("", ERR_SD_CONNREFUSED);
  sms_log_error(__FILE__.':'.__LINE__.": me_connect() failed\n");
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  return $ret;
}

$on_error_fct = 'cisco_disconnect';

$result = '';
$cmds = explode(',', $smsexec_list);
foreach ($cmds as $cmd)
{
  if (!empty($specific_cmds[$cmd]))
  {
    // call specific function
    $specific_cmds[$cmd]();
  }
  else
  {
    // send command
    $result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'lire dans sdctx', 36000000, false);
  }
}

unset($on_error_fct);
#me_disconnect();
me_cli_disconnect();

sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;

?>