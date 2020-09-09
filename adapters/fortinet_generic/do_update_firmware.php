<?php

// Verb JSUPDATEFIRMWARE

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'adaptor.php');
require_once load_once('fortinet_generic', 'fortinet_generic_connect.php');
require_once load_once('fortinet_generic', 'fortinet_generic_configuration.php');


$param = '';

$index = 1;
$p = "param$index";
$update_type="";
while (!empty($$p))
{
  // Parameters
  $udpt = strpos($$p, 'REST-API');
  if ($udpt !== false)
  {
    $update_type = 'REST-API';
  }

  $index++;
  $p = "param$index";
}

if (!empty($update_type) && ($update_type === 'REST-API'))
{
  $update_firmware_fct = 'update_firmware_rest';
}
else
{
  $update_firmware_fct = 'update_firmware';
}

try
{
  $status_type = 'FIRMWARE';

  $ret = sms_sd_lock($sms_csp, $sms_sd_info);
  if ($ret !== 0)
  {
    sms_log_info(__FILE__." can not lock \n");

    sms_send_user_error($sms_csp, $sdid, "", $ret);
    sms_close_user_socket($sms_csp);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

  // Asynchronous mode, the user socket is now closed, the results are written in database
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);

  $ret = fortinet_generic_connect();
  if ($ret != SMS_OK)
  {
    throw new SmsException("", $ret);
  }

  $conf = new fortinet_generic_configuration($sdid);

  $status_message = '';

  $ret = $conf->$update_firmware_fct($status_message, $status_type);

  fortinet_generic_disconnect();

  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $status_message);
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  status_progress("Updating asset", $status_type);

  sms_sd_forceasset($sms_csp, $sms_sd_info);
  sleep(60);

  sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);

  sms_sd_unlock($sms_csp, $sms_sd_info);
}
catch (Exception | Error $e)
{
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), $status_type, 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  fortinet_generic_disconnect();
  return SMS_OK;
}

return SMS_OK;

?>
