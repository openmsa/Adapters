<?php
/*
 * Version: $Id: do_update_conf.php 38235 2010-12-27 08:49:22Z tmt $
 * Created: Jun 30, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 *
 */

// Enter Script description here
require_once 'smsd/sms_common.php';
require_once load_once('arista_eos', 'arista_eos_configuration.php');
require_once load_once('arista_eos', 'common.php');

$param = '';

$index = 1;
$p = "param$index";
while (!empty($$p))
{
  // Parameters
  if (strpos($$p, 'file_server_addr=') !== false)
  {
    $file_server_addr = str_replace('file_server_addr=', '', $$p);
  }
  else if (empty($param))
  {
    $param = "$param ${$p}";
  }
  $index++;
  $p = "param$index";
}


if (empty($file_server_addr))
{
  // When address is not specified, the file server is the SOC
  $is_local_file_server = false;
}
else
{
  // When address is specified, the file server is a local file server
  $is_local_file_server = true;
}

try
{
  $status_type = 'FIRMWARE';

  $ret = sms_sd_lock($sms_csp, $sms_sd_info);
  if ($ret !== 0)
  {
    sms_send_user_error($sms_csp, $sdid, "", $ret);
    sms_close_user_socket($sms_csp);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);
  // Asynchronous mode, the user socket is now closed, the results are written in database
  
  arista_eos_connect();

  $conf = new AristaEosConfiguration($sdid);

  $ret = $conf->update_firmware($param);
  if ($ret !== SMS_OK)
  {
    throw new SmsException('', $ret, __FILE__ . __LINE__);
  }

  sms_sd_forceasset($sms_csp, $sms_sd_info);
  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  arista_eos_disconnect();
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  arista_eos_disconnect();
  return SMS_OK;
}

return SMS_OK;

?>
