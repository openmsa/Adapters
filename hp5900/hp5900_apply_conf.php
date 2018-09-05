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
require_once load_once('hp5900', 'apply_errors.php');
require_once load_once('hp5900', 'hp5900_configuration.php');

require_once "$db_objects";

define('DELAY', 200000);

function device_apply_conf($configuration, $push_to_startup = false)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sms_sd_info;
  global $sendexpect_result;
  global $apply_errors;

  $network = get_network_profile();
  $SD = &$network->SD;

  $ret = save_result_file($configuration, "conf.applied");
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $SMS_OUTPUT_BUF = '';
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  $file_name = "{$sdid}.cfg";
  $full_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;

  $ret = save_file($configuration, $full_name);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  
  //SEND LINE BY LINE
  
  //[SWF#117] NCOS Bugfix 2017.09.21 MODIFIED START
  $hostname = substr($sms_sd_ctx->getPrompt(), 1, strlen($sms_sd_ctx->getPrompt())-2);
  $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "system-view", "[$hostname]");

  //NCOS Bugfix 2017.09.08 MODIFIED START
  $buffer = $configuration;
  $buffer = preg_replace('/^\h*\v+/m', '', $buffer); //remove blank lines
  $line = get_one_line($buffer);
  $ERROR_BUFFER = "";
  while ($line)
  {
    $line = trim($line);
    if (strpos($line, "!") === 0)
    {
      echo "$sdid: $line\n";
    }
    else
    {
      //sms_log_info("DEBUGGING: \"$hostname\" / line=\"$line\" response=\"$expect_response\"\n");
      //if (preg_match('/^q(|u(|i(|t)))$/', $line) == 1 && strrchr($expect_response, "[$hostname]"))
      if (preg_match('/^q(|u(|i(|t)))$/', $line) == 1 && preg_match("/.*\[$hostname\]$/", $expect_response) == 1)
      {
        $ERROR_BUFFER .= "\n!\nError: \"$line\" command will exit from system-view mode.\n";
        break;
      }
      //elseif (preg_match('/res(|e(|t)).*/', $line) === 0 && preg_match('/re(|t(|u(|r(|n))))/', $line) == 1)
      elseif (preg_match('/^res(|e(|t)).*/', $line) === 0 && // reset 
          preg_match('/^rem(|a(|r(|k))).*/', $line) === 0 && // remark
          preg_match('/^red(|i(|r(|e(|c(|t))))).*/', $line) === 0 && // redirect
          preg_match('/^re(|t(|u(|r(|n))))/', $line) == 1) //fixed 2017.10.02 NCOS
      {
        $ERROR_BUFFER .= "\n!\nError: \"$line\" command will exit from system-view mode.\n";
        break;
      }
      else
      {
        $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $line, "]");

        // update $hostname in case sysname command was issued
        if (strstr($expect_response, " ", true) === "sysname")
        {
          preg_match('/.*\[(?P<hostname>.+)\].*/', $expect_response, $matches);
          $hostname = $matches['hostname'];
          //sms_log_info("TEST: [expect] $expect_response ; [hostname] $hostname");
        }
      }

      $SMS_OUTPUT_BUF .= $sendexpect_result;
      
      foreach ($apply_errors as $apply_error)
      {
        if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0)
        {
          $ERROR_BUFFER .= "!\n";
          $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
          $ERROR_BUFFER .= "\n";
        }
      }
    }
    $SMS_OUTPUT_BUF = '';
    $line = get_one_line($buffer);
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
  $need_save = TRUE;

  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", ">");
  //[SWF#117] NCOS Bugfix 2017.09.21 MODIFIED END 

  // Save config
  if ($need_save === TRUE)
  {
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "system-view", "]");
    //[SWF#112] NCOS Bugfix 2017.09.07 MODIFIED START
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "save", "Are you sure? [Y/N]:");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", "unchanged, press the enter key):");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "", "overwrite? [Y/N]:");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", "]");
    //[SWF#112] NCOS Bugfix 2017.09.07 MODIFIED END
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", ">");
  }
  $sms_sd_ctx-> extract_prompt(); 
  //NCOS Bugfix 2017.09.08 MODIFIED END
  
  return SMS_OK;
}

?>
