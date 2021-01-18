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
require_once load_once('ovm_manager', 'common.php');
require_once load_once('ovm_manager', 'apply_errors.php');
require_once load_once('ovm_manager', 'ovm_manager_configuration.php');
require_once load_once('ovm_manager', 'ovm_manager_connect.php');

require_once "$db_objects";

define('DELAY', 200000);
function ovm_manager_apply_conf($configuration, $is_ztd)
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

  $ret = SMS_OK;

  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = "Password:";
  $tab[2] = "#";
  $tab[3] = "$";

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

      if ($index === 1)
      {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $SD->SD_PASSWD_ENTRY, $tab);
        $SMS_OUTPUT_BUF .= $sendexpect_result;
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
        }
      }
    }
    $line = get_one_line($buffer);
  }
  if (!empty($ERROR_BUFFER))
  {
    save_result_file($ERROR_BUFFER, "conf.error");
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

