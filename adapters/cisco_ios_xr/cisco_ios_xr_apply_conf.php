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

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf exclusive", "(config)#", DELAY);

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

          sms_log_error ( __FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n" );
          $SMS_OUTPUT_BUF = '';
          $ret = ERR_SD_CMDFAILED;
        }
      }
    }
    $line = get_one_line($buffer);
  }

  // confirm we save the configuration
  unset($tab);
  $tab[0] = "Failed to commit";
  $tab[1] = "proceed with this commit anyway? [no]:";
  $tab[2] = ")#";

  $line = 'commit comment "MSA: apply conf"';
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
  $SMS_OUTPUT_BUF = $sendexpect_result;

  // if the command fails or request confirmation
  if ($index !== 0)
  {
    $ERROR_BUFFER .= "!";
    $ERROR_BUFFER .= "\n";
    $ERROR_BUFFER .= $line;
    $ERROR_BUFFER .= "\n";
    $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
    $ERROR_BUFFER .= "\n";
  }

  if ($index === 0)
  {
    // Failed to commit one or more configuration items during a pseudo-atomic operation.
    // All changes made have been reverted. Please issue 'show configuration failed [inheritance]'
    // from this session to view the errors
    $line = 'show configuration failed';
    $SMS_OUTPUT_BUF = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, ")#", DELAY);

    $ERROR_BUFFER .= $line;
    $ERROR_BUFFER .= "\n";
    $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
    $ERROR_BUFFER .= "\n";
  }
  else if ($index === 1)
  {
    // One or more commits have occurred from other configuration sessions since this session started
    // or since the last commit was made from this session.
    // You can use the 'show configuration commit changes' command to browse the changes.
    // Do you wish to proceed with this commit anyway? [no]:
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "yes", ")#", DELAY);
  }

  // we leave all conf (and potential submodes)
  unset($tab);
  $tab[0] = "#";
  $tab[1] = "commit them before exiting(yes/no/cancel)? [cancel]:";

  $line = 'end';
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
  $SMS_OUTPUT_BUF = $sendexpect_result;

  // if the command fails or request confirmation
  if ($index !== 0)
  {
    $ERROR_BUFFER .= "!";
    $ERROR_BUFFER .= "\n";
    $ERROR_BUFFER .= $line;
    $ERROR_BUFFER .= "\n";
    $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
    $ERROR_BUFFER .= "\n";
  }

  if ($index === 1)
  {
    // Uncommitted changes found, commit them before exiting(yes/no/cancel)? [cancel]:
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "no", ")#", DELAY);
  }

  // Refetch the prompt cause it can change during the apply conf
  extract_prompt();

  // Exit from config mode
  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = ")#";
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, DELAY);
  $SMS_OUTPUT_BUF = $sendexpect_result;
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
