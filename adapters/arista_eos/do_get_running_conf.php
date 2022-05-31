<?php
/*
* Created:  May 24, 2022
*/

// Get router configuration

require_once 'smsd/sms_common.php';

require_once load_once('arista_eos', 'arista_eos_configuration.php');
require_once load_once('arista_eos_ios', 'adaptor.php');


try {

  $ret = sd_connect();
  if ($ret == SMS_OK)
  {
    $conf = new AristaEosConfiguration($sdid);
    $running_conf = $conf->get_running_conf();
  }

  if (!empty($running_conf))
  {
    sms_send_user_ok($sms_csp, $sdid, $running_conf);
  }
  else
  {
    sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
  }
  sd_disconnect();
}
catch(Exception | Error $e)
{
  sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
  sd_disconnect();
}

return SMS_OK;

?>