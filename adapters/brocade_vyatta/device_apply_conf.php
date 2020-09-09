<?php
/*
 * Version: $Id$
 * Created: May 13, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('brocade_vyatta', 'device_apply_conf_errors.php');
require_once load_once('brocade_vyatta', 'device_configuration.php');

require_once "$db_objects";

define('DELAY', 200000);
function device_apply_conf($configuration, $push_to_startup = false)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sms_sd_info;
  global $sendexpect_result;
  global $apply_errors;

  $configuration = trim($configuration);
  if (empty($configuration))
  {
    return SMS_OK;
  }

  debug_dump($configuration, 'CONFIGURATION TO APPLY');

  $network = get_network_profile();
  $SD = &$network->SD;

  $ret = save_result_file($configuration, "conf.applied");
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $SMS_OUTPUT_BUF = '';

  sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'configure', '#');

  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = "#";

  $buffer = $configuration;
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    $line = trim($line);
    if (strpos($line, "#") === 0)
    {
      echo "$sdid: $line\n";
    }
    else
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab);
      $SMS_OUTPUT_BUF .= $sendexpect_result;
    }
    $line = get_one_line($buffer);
  }

  // Exit from config mode
  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = 'exit discard';
  $tab[2] = "#";
  $SMS_OUTPUT_BUF .= $sendexpect_result;
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab);
  for ($i = 1; ($i <= 10) && ($index === 2); $i++)
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
  }
  if ($index !== 0)
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "commit", $tab);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "save", $tab);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
    if ($index === 1) {
	$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit discard", $tab);
	$SMS_OUTPUT_BUF .= $sendexpect_result;
    }
  }

  save_result_file($SMS_OUTPUT_BUF, "conf.error");

  foreach ($apply_errors as $apply_error)
  {
    if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
      return ERR_SD_CMDFAILED;
    }
  }

  return $ret;
}

?>

