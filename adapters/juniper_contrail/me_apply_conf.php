<?php

require_once 'smsd/sms_common.php';
require_once load_once('juniper_contrail', 'me_connect.php');

require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 */
function me_apply_conf($configuration)
{
  global $sms_sd_ctx;

  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');
  $SMS_OUTPUT_BUF = '';

  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);

    if (!empty($line))
    {
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, '');

      $SMS_RETURN_BUF = json_encode($res);
    }
    $line = get_one_line($configuration);
  }

  return SMS_OK;
}

?>