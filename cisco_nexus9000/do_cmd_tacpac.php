<?php
/*
 * Version: $Id$
 * Created: Nov 03, 2011
 * Available global variables
 *  $sms_sd_info     sd_info structure
 *  $sms_csp         pointer to csp context to send response to user
 *  $sdid            id of the device
 *  $optional_params optional parameters
 *  $sms_module      module name (for patterns)
 */

// Verb JSACMD REBOOT

require_once 'smsd/sms_common.php';

require_once load_once('cisco_nexus9000', 'cisco_nexus_connect.php');
require_once load_once('cisco_nexus9000', 'cisco_nexus_configuration.php');
require_once load_once('cisco_nexus9000', 'apply_errors.php');
require_once "$db_objects";

function tacpac($event)
{
    global $sdid;
    global $sms_sd_ctx;
    global $sms_sd_info;
    global $sendexpect_result;
    global $apply_errors;
    global $sms_csp;
    global $optional_params;
    print_r($optional_params);
    $params = preg_split('/\s/', $optional_params);
    debug_dump($params, "printing params\n");

    $ip_address = $params[0];
    $username = $params[1];
    $password = $params[2];
    $file_path = $params[3];
    
  status_progress('Getting tac-pac information', $event);

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "tac-pac", "#", 900000);
  $buffer =  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir volatile:// | include show_", "#");
  if (strpos($buffer, "show_tech_out.gz") === false){
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    throw new SmsException($sendexpect_result, ERR_SD_CMDFAILED);     
  }

  unset($tab);
  $tab[0] = "(yes/no)?";
  $tab[1] = "assword:";
  $tab[2] = $sms_sd_ctx->getPrompt();
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy volatile:///show_tech_out.gz sftp://".$username."@".$ip_address.$file_path." vrf management", $tab);
  if ($index === 0)
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "yes", $tab);
    if ($index !== 1){
        sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
        sms_sd_unlock($sms_csp, $sms_sd_info);
        throw new SmsException($sendexpect_result, ERR_SD_CMDFAILED);
    }
    sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $password, "#");
  }
  elseif ($index === 1)
  {
    sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $password, "#");
  }
  return SMS_OK;
}
try {
    $status_type = 'TACPAC';

    $ret = sms_sd_lock($sms_csp, $sms_sd_info);
    if ($ret !== 0) {
      sms_send_user_error($sms_csp, $sdid, "", $ret);
      sms_close_user_socket($sms_csp);
      return SMS_OK;
    }

    sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

    // Asynchronous mode, the user socket is now closed, the results are written in database
//    sms_send_user_ok($sms_csp, $sdid, "");
//    sms_close_user_socket($sms_csp);

    // Connect to the device
    $ret = cisco_nexus_connect();
    if ($ret !== SMS_OK)
    {
      sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $e->getMessage());
      sms_sd_unlock($sms_csp, $sms_sd_info);
      cisco_nexus_disconnect();
      return SMS_OK;
    }
    $ret = tacpac($status_type);
    cisco_nexus_disconnect(true);
} catch (Exception $e) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $e->getMessage());
    sms_sd_unlock($sms_csp, $sms_sd_info);
    cisco_nexus_disconnect();
    return SMS_OK;
}

if ($ret !== SMS_OK) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
?>