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
 *  $addon             Addon name
 */

// Enter Script description here

require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('cisco_isr', 'adaptor.php');
require_once "$db_objects";

$network = get_network_profile();
$SD = &$network->SD;

// Lock
$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

$ret = addon_connect($addon, true);
if ($ret !== SMS_OK)
{
  return false;
}

$result = '';

// Keep commands
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
    $result .= addon_execute_command($addon, $cmd, "#");
  }
}

addon_disconnect();

sms_sd_unlock($sms_csp, $sms_sd_info);
sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;

?>