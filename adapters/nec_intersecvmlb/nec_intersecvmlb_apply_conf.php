<?php
/*
 * Version: $Id: nec_intersecvmlb_apply_conf.php 113 2016-12-06 15:40:24Z domeki $
* Created: May 13, 2011
* Available global variables
*  $sms_csp            pointer to csp context to send response to user
*  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info        pointer to sd_info structure
*  $SMS_RETURN_BUF     string buffer containing the result
*/

// Transfer the configuration file on the router

require_once 'smsd/sms_common.php';
require_once load_once('nec_intersecvmlb', 'common.php');
require_once load_once('nec_intersecvmlb', 'apply_errors.php');
require_once load_once('nec_intersecvmlb', 'nec_intersecvmlb_configuration.php');

require_once "$db_objects";

define('DELAY', 60000);

function nec_intersecvmlb_apply_conf(&$configuration)
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
  $ERROR_BUFFER = '';

  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = ') #';
  $tab[2] = '> ';
  $tab[3] = '(y/n)';
  
  $buffer = $configuration;
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    $line = trim($line);
    if (strpos($line, '#') !== 0)
    {
      if(strpos($line, 'set hostname') !== false)
      {
    	$tab[4] = '#'; //prompt may change on hostname update
    	var_dump($tab);
      }
      $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $line, $tab, DELAY);
      $SMS_OUTPUT_BUF .= $sendexpect_result;
      
      if($index === 3)
      {
      	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", $tab, DELAY);
      	$SMS_OUTPUT_BUF .= $sendexpect_result;
      }
      else if ($index === 4)
      {
      	$sms_sd_ctx->do_store_prompt();
      	$tab[0] = $sms_sd_ctx->getPrompt();
      	unset($tab[4]);
      }

      foreach ($apply_errors as $apply_error)
      {
        if (preg_match($apply_error, $sendexpect_result, $matches) > 0)
        {
          $ERROR_BUFFER .= "!";
          $ERROR_BUFFER .= "\n";
          $ERROR_BUFFER .= $line;
          $ERROR_BUFFER .= "\n";
          $ERROR_BUFFER .= $apply_error;
          $ERROR_BUFFER .= "\n";
          // $ERROR_BUFFER .= $sendexpect_result;
          // $ERROR_BUFFER .= "\n";
          $SMS_OUTPUT_BUF = '';
        }
      }
    }
    $line = get_one_line($buffer);
  }

  /* YDU : Pb desynchronisation
  // Exit from config mode
  $i = 0;
  unset($tab);
  $tab[0] = 'Unknown action';
  $tab[1] = $sms_sd_ctx->getPrompt();
  $tab[2] = ') #';
  do
  {
    $i++;
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $tab, DELAY);
  } while (($index == 1 || $index == 2) && ($i < 10));

  // Refetch the prompt cause it can change during the apply conf
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'config system console', '(console) #');
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', '#');
  $sms_sd_ctx->setPrompt(trim($buffer));
  $sms_sd_ctx->setPrompt(substr(strrchr($buffer, "\n"), 1));
  */
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
/* YDU
  $date = date("Y-m-d");
  $time = date("H:i:s");
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "execute date $date");
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "execute time $time");
*/

  return SMS_OK;
}

?>
