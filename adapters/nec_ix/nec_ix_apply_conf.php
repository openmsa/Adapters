<?php
/*
 * Version: $Id$
* Created: Dec 06, 2018
* Available global variables
*  $sms_csp            pointer to csp context to send response to user
*  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info        pointer to sd_info structure
*  $SMS_RETURN_BUF     string buffer containing the result
*/

// Transfer the configuration file on the router
// First try to use SCP then TFTP

require_once 'smsd/sms_common.php';
require_once load_once('nec_ix', 'apply_errors.php');
require_once load_once('nec_ix', 'nec_ix_configuration.php');
require_once load_once('nec_ix', 'common.php');
require_once "$db_objects";

define('DELAY', 200000);

function nec_ix_apply_conf($configuration, $push_to_startup = false)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sms_sd_info;
  global $sendexpect_result;
  global $apply_errors;

  if (strlen($configuration) === 0)
  {
    return SMS_OK;
  }

  $ret = save_result_file($configuration, "conf.applied");
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $SMS_OUTPUT_BUF = '';
  $ERROR_BUFFER = '';

  $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf", '(config)#');

  unset($tab);
  $tab[0] = "(config)#"; 
  $tab[1] = "#";
  $tab[2] = "[y/n]";

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
      //if (($line === "exit" || $line === "quit") && strrchr($expect_response,"(config)#"))
      //if (preg_match('/^q(|u(|i(|t)))$|^ex(|i(|t))$|^en(|d)$/', $line) == 1 && strrchr($expect_response,"(config)#"))
      if (preg_match('/^q(|u(|i(|t)))$|^ex(|i(|t))$|^en(|d)$/', $line) == 1 && 
					preg_match('/.*\(config\)# $/',$expect_response) === 1)
      {
        //sms_log_info("Notice: double quit/exit detected. Operation terminated.\n");
	//$ERROR_BUFFER .= "\n!\nError: double quit/exit detected. Operation terminated.\n";
	$ERROR_BUFFER .= "\n!\nError: \"$line\" command will exit from configure mode.\n";
        break;
      }

      $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $line,$tab);
      if ($index === 2)
      { 
        $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y","#");
      }
      else
      {
        $expect_response = $tab[$index];
      }
      sms_log_info("EXPECT RESPONSE: ".$expect_response);

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

  // Exit from config mode
  $need_save = TRUE;
  

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

  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", '#');

  // Save config
  if ($need_save === TRUE)
  {	
	sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf", '(config)#');
	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "write mem", "(config)#");
	sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", '#');
  }
  extract_prompt(); 

  return SMS_OK;
}

?>
