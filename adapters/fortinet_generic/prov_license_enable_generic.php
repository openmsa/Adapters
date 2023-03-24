<?php
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'common.php');
require_once "$db_objects";

// -------------------------------------------------------------------------------------
// PROVISIONING
// -------------------------------------------------------------------------------------
function prov_license_enable_generic($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $ipaddr;
  global $login;
  global $passwd;
  global $port;
  global $sms_sd_ctx;

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exec update-now", "#");

  $conf = new fortinet_generic_configuration($sdid, true);

  fortinet_generic_disconnect();
  $ret = $conf->wait_until_device_is_up(60);
  
  if ($ret == SMS_OK)
  {
    fortinet_generic_connect($ipaddr, $login, $passwd, $port);
  }
  else
  {
    throw new SmsException("Connection Failed", $ret);
  }

  // Wait for license status
  $wait = 20;
  $loop = 600 / $wait;
  while ($loop > 0)
  {
  	try {
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'get system status', '#');
    if (strpos($buffer, 'License Status: Valid') !== false)
    {
      return SMS_OK;
    }
    $loop--;
    sleep($wait);
  	} catch (Exception $e) {
  		echo "reconnect to handle the license upgrade/downgrade case where SSH key is regenerated";
  		fortinet_generic_connect($ipaddr, $login, $passwd, $port);
  	}
  }
  throw new SmsException("License Enable Failed", ERR_SD_LICENSE_UPDATE);
}


?>
