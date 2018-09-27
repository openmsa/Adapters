<?php

/*
 * Available global variables
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_sd_info sd_info structure
 * $sdid
 * $sms_module module name (for patterns)
 * $SMS_RETURN_BUF string buffer containing the result
 */

// Get router configuration, not JSON response format
require_once 'smsd/sms_common.php';

require_once load_once('f5_bigip', 'f5_bigip_connect.php');
require_once load_once('f5_bigip', 'f5_bigip_backup_configuration.php');

$date = date('c');
$SMS_RETURN_BUF = "{$date}";

try
{
  $loop = 20;
  while ($loop > 0) {
    $ret = f5_bigip_connect();
    if ($ret == SMS_OK) {
      break;
    }
    sleep(10); // wait for ssh to come up
    $loop --;
  }

  if ($ret != SMS_OK)
  {
    return $ret;
  }

  $conf = new f5_bigip_backup_configuration($sdid);

  $ret = $conf->backup_conf();
  if ($ret !== SMS_OK)
  {
    f5_bigip_disconnect();
    return $ret;
  }

  f5_bigip_disconnect();
}
catch (Exception $e)
{
  f5_bigip_disconnect();
  return $e->getCode();
}

$SMS_RETURN_BUF = $conf->get_return_buf();

return SMS_OK;
?>
