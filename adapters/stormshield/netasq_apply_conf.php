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
require_once 'smsd/sms_common.php';
require_once load_once('stormshield', 'common.php');
require_once load_once('stormshield', 'apply_errors.php');

require_once "$db_objects";

define('DELAY', 60000);
function netasq_apply_conf(&$configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sms_sd_info;
  global $sendexpect_result;
  global $apply_errors;

  $network = get_network_profile();
  $SD = &$network->SD;

  $validate_passwd = sha1("UBIqube-$sdid"); // see netasq_configuration.php

  $configuration = trim($configuration);
  if (empty($configuration))
  {
    return SMS_OK;
  }

  if ($SD->SD_HSRP_TYPE !== 0)
  {
    $configuration .= "\n#The following line is automatically added by the MSA for a cluster\nha sync\n";
  }

  $configuration .= "\n#The following lines are added by the MSA\nconfig status remove\nconfig status validate password=$validate_passwd";

  $ret = save_result_file($configuration, "conf.applied");
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $SMS_OUTPUT_BUF = '';
  $ERROR_BUFFER = '';
  unset($hostname_set);
  $hostname_set = 0;
  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();

  sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'modify on force');

  $ignore_error = false;
  $buffer = $configuration;
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    $line = trim($line);
    if (empty($line))
    {
      $line = get_one_line($buffer);
      continue;
    }
    if (strpos($line, '#') !== 0)
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab, DELAY);
      $SMS_OUTPUT_BUF .= $sendexpect_result;

      if (!$ignore_error)
      {
        foreach ($apply_errors as $apply_error)
        {
          if (preg_match($apply_error, $sendexpect_result, $matches) > 0)
          {
            $ERROR_BUFFER .= "!\n";
            $ERROR_BUFFER .= $sendexpect_result;
            $ERROR_BUFFER .= "\n";
            $SMS_OUTPUT_BUF = '';
          }
        }
      }
    }
    else if (strpos($line, '#IGNORE_ERROR_BEGIN') === 0)
    {
      $ignore_error = true;
    }
    else if (strpos($line, '#IGNORE_ERROR_END') === 0)
    {
      $ignore_error = false;
    }
    $line = get_one_line($buffer);
  }

  sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'modify off');

  if (!empty($ERROR_BUFFER))
  {
    save_result_file($ERROR_BUFFER, "conf.error");
    $SMS_OUTPUT_BUF = $ERROR_BUFFER;
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
    return ERR_SD_CMDFAILED;
  }
  else
  {
    save_result_file($SMS_OUTPUT_BUF, "conf.error");
    $SMS_OUTPUT_BUF = '';
  }

  return SMS_OK;
}

?>