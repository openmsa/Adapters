<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('versa_analytics', 'versa_analytics_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function versa_analytics_apply_conf($configuration)
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
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, trim($line), '/response');
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
    }
    $line = get_one_line($configuration);
  }

  // commit
  if ($SD->MOD_ID === 136)
  {
    $cmd = "<commit><partial><vsys><member>{$SD->SD_HOSTNAME}</member></vsys></partial></commit>";
  }
  else
  {
    $cmd = "<commit></commit>";
  }
  $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=commit&cmd='.urlencode($cmd));
  if (!empty($result->result) && !empty($result->result->job))
  {
    $job = $result->result->job;

    do
    {
      sleep(2);
      $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=op&cmd='.urlencode("<show><jobs><id>{$job}</id></jobs></show>"));
      if (!empty($operation) && $result->result->job->status == 'ACT')
      {
        status_progress("progress {$result->result->job->progress}%", $operation);
      }
    } while ($result->result->job->status != 'FIN');
    if (!empty($SMS_OUTPUT_BUF))
    {
      $SMS_OUTPUT_BUF .= $result->result->job->asXml();
    }
  }
  save_result_file($SMS_OUTPUT_BUF, "conf.error");
  if (!empty($SMS_OUTPUT_BUF))
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
    return ERR_SD_CMDFAILED;
  }

  return SMS_OK;
}

?>