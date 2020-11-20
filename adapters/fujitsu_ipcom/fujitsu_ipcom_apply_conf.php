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
require_once load_once('fujitsu_ipcom', 'common.php');
require_once load_once('fujitsu_ipcom', 'apply_errors.php');
require_once load_once('fujitsu_ipcom', 'fujitsu_ipcom_configuration.php');

require_once "$db_objects";

define('DELAY', 200000);
function fujitsu_ipcom_apply_conf($configuration, $push_to_startup = false)
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
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  $file_name = "{$sdid}.cfg";
  $full_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;

  $ret = save_file($configuration, $full_name);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  // ---------------------------------------------------
  // Line by line mode configuration
  // ---------------------------------------------------
    echo "Line by line mode configuration\n";
    $ERROR_BUFFER = '';

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#", DELAY);

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "load running-config", "(edit)#", DELAY);

    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = ")#";
    $tab[2] = "y|[n])";
    $tab[3] = "[confirm]";
    $tab[4] = "[no]:";

    $buffer = $configuration;
    $line = get_one_line($buffer);
    while ($line !== false)
    {
      $line = trim($line);
      if (strpos($line, "!") === 0)
      {
        echo "$sdid: $line";
      }
      else
      {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
        $SMS_OUTPUT_BUF .= $sendexpect_result;
        if (($index === 2) || ($index === 3))
        {
          sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab, DELAY);
          $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
        else if ($index === 4)
        {
          sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab, DELAY);
          $SMS_OUTPUT_BUF .= $sendexpect_result;
        }

        echo "BUFFER====================================$SMS_OUTPUT_BUF\n";
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

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, '', '(edit)#');

    unset($tab);
    $tab[0] = ")#";
    $tab[1] = "y|[n])";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "commit", $tab, DELAY);
    if($index === 1)
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab, DELAY);
     $SMS_OUTPUT_BUF .= $sendexpect_result;
    }

    if($index === 1)
    {
      sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", '#');
      $SMS_OUTPUT_BUF .= $sendexpect_result;
    }
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab, DELAY);
    if($index === 1)
        {
              sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", '#');
              $SMS_OUTPUT_BUF .= $sendexpect_result;
        }
     echo "BUFFER2====================================$SMS_OUTPUT_BUF\n";
//ipcom device show actual error only at the commit of the configuration. so capture the whole error line from device
 foreach ($apply_errors as $apply_error)
{
 if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0)
              {
               $patter = substr($apply_error,1,-1);
               $pattern2 = "/$patter.*/";
               echo "+++++++++++++++++++$pattern2\n";
              if (preg_match($pattern2, $SMS_OUTPUT_BUF, $match)>0)
              {
                           $ERROR_BUFFER .= "!";
                           $ERROR_BUFFER .= "\n";
                           $ERROR_BUFFER .= $match[0];
                           $ERROR_BUFFER .= "\n";
                          $ERROR_BUFFER .= $apply_error;
                           $ERROR_BUFFER .= "\n";
                          $SMS_OUTPUT_BUF = '';
                          }
                  }
  }
    // Refetch the prompt cause it can change during the apply conf
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit', '#');
    $sms_sd_ctx->setPrompt(trim($buffer));
    $sms_sd_ctx->setPrompt(substr(strrchr($buffer, "\n"), 1));

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
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]");
      return ERR_SD_CMDFAILED;
    }
    else
    {
    //if no error found empty the device buffer
    $SMS_OUTPUT_BUF = "";
     save_result_file("No error found during the application of the configuration", "conf.error");
    }

   //$ret = func_write();

    return $ret;
}

?>
