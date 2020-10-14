<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('cisco_asa_rest', 'cisco_asa_rest_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function cisco_asa_rest_apply_rest_conf($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $operation;
  global $SD;

  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');
  $SMS_OUTPUT_BUF = '';

  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    if (!empty($line))
    {
      echo "$line\n";

      // $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, trim($line), '/response');
/*
      $sms_sd_ctx->curl();


      if (trim($res['status']) !== 'success')
      {
        $line = urldecode($line);
        if (!empty($res->msg->line->line))
        {
          $msg = (String)$res->msg->line->line;
        }
        else if (!empty($res->msg->line))
        {
          $msg = (String)$res->msg->line;
        }
        else if (!empty($res->result->msg))
        {
          $msg = (String)$res->result->msg;
        }
        $SMS_OUTPUT_BUF .= "{$line}\n\n{$msg}\n";
      }
*/
    }
    $line = get_one_line($configuration);
  }

  // commit
  save_result_file($SMS_OUTPUT_BUF, "conf.error");
  if (!empty($SMS_OUTPUT_BUF))
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
    return ERR_SD_CMDFAILED;
  }

  return SMS_OK;
}

?>
