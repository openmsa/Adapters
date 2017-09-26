<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('juniper_srx', 'juniper_srx_connect.php');
require_once load_once('juniper_srx', 'apply_errors.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function juniper_srx_apply_conf($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $SMS_OUTPUT_BUF;
  global $SD;

  if (strlen($configuration) === 0)
  {
    return SMS_OK;
  }

  debug_dump($configuration, 'CONFIG TO APPLY');

  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');

  $SMS_OUTPUT_BUF = '';
  $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "configure private", "#");

  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    if (!empty($line))
    {
      $SMS_OUTPUT_BUF .= $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $line, "#");
    }
    $line = get_one_line($configuration);
  }
  save_result_file($SMS_OUTPUT_BUF, "conf.error");

  foreach ($apply_errors as $apply_error)
  {
    if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "rollback");
      return ERR_SD_CMDFAILED;
    }
  }

  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "commit");
  unset($tab);
  $tab[0] = 'commit complete';
  $tab[1] = 'error: cannot commit an empty configuration';
  $tab[2] = 'commit failed';
  $tab[3] = $sms_sd_ctx->getPrompt();
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
  $SMS_OUTPUT_BUF .= $sendexpect_result;
  $SMS_OUTPUT_BUF = str_replace("\n", "\\n", $SMS_OUTPUT_BUF);

  if ($index !== 0)
  {
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "rollback");
    return ERR_SD_CMDFAILED;
  }

  foreach ($apply_errors as $apply_error)
  {
    if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
      return ERR_SD_CMDFAILED;
    }
  }

  $cmd_quote = str_replace("\"", "'", $SMS_OUTPUT_BUF);
  $cmd_return = str_replace("\n", "", $cmd_quote);
  $SMS_OUTPUT_BUF = $cmd_return;

  return SMS_OK;
}

?>
