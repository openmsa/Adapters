<?php


// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('cisco_ios_xr', 'common.php');
require_once load_once('cisco_ios_xr', 'apply_errors.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_configuration.php');

require_once "$db_objects";

define('DELAY', 200000);
function cisco_ios_xr_apply_conf($configuration, $push_to_startup = false)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sms_sd_info;
  global $sendexpect_result;
  global $apply_errors;

  if (trim($configuration) === '')
  {
    return SMS_OK;
  }

  $network = get_network_profile();
  $SD = &$network->SD;

  $ret = save_result_file($configuration, "conf.applied");
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $SMS_OUTPUT_BUF = '';

  // ---------------------------------------------------
  // Line by line mode configuration
  // ---------------------------------------------------
  $ret = SMS_OK;

  echo "Line by line mode configuration\n";
  $ERROR_BUFFER = '';

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#", DELAY);

  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = ")#";
  $tab[2] = "]?";
  $tab[3] = "[confirm]";
  $tab[4] = "[no]:";

  $buffer = $configuration;
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    $line = trim($line);
    if (strpos($line, "!") === 0)
    {
      echo "$sdid: $line\n";
    }
    else
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
      $SMS_OUTPUT_BUF .= $sendexpect_result;
      if (($index === 2) || ($index === 3))
      {
        sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, DELAY);
        $SMS_OUTPUT_BUF .= $sendexpect_result;
      }
      else if ($index === 4)
      {
        sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "yes", $tab, DELAY);
        $SMS_OUTPUT_BUF .= $sendexpect_result;
      }

      foreach ($apply_errors as $apply_error)
      {
        if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0)
        {
          $ERROR_BUFFER .= "!";
          $ERROR_BUFFER .= "\n";
          $ERROR_BUFFER .= $line;
          $ERROR_BUFFER .= "\n";
          $ERROR_BUFFER .= $apply_error;
          $ERROR_BUFFER .= "\n";
          $SMS_OUTPUT_BUF = '';
        }
      }
    }
    $line = get_one_line($buffer);
  }

  // confirm we save the configuration
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'commit comment "MSA: APPLY CONF"', ")#");
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', '#');

  // Refetch the prompt cause it can change during the apply conf
  extract_prompt();

  // Exit from config mode
  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = ")#";
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, DELAY);
  $SMS_OUTPUT_BUF .= $sendexpect_result;
  for ($i = 1; ($i <= 10) && ($index === 1); $i++)
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab, DELAY);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
  }

  if (!empty($ERROR_BUFFER))
  {
    save_result_file($ERROR_BUFFER, "conf.error");
    $SMS_OUTPUT_BUF = $ERROR_BUFFER;
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
    return ERR_SD_CMDFAILED;
  }
  else
  {
    save_result_file("No error found during the application of the configuration", "conf.error");
  }

  return $ret;
}

?>
