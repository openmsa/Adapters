<?php
/*
 * Date : Oct 19, 2007
*/

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('linux_generic', 'linux_generic_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported"
);

// extract the prompt
function extract_prompt()
{
  global $sms_sd_ctx;

  /* pour se synchroniser et extraire le prompt correctement */
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'configure', '#');
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'end', '>');
  $buffer = trim($buffer);
  $buffer = substr(strrchr($buffer, "\n"), 1);  // recuperer la derniere ligne
  $sms_sd_ctx->setPrompt($buffer);
}

// function to be called after the configuration transfer
function copy_to_running($cmd)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $error_list;

  unset($error_list);

  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = '[no]:';
  $tab[2] = ']?';
  $tab[3] = '[confirm]';
  $tab[4] = '[yes/no]';
  $tab[5] = '[yes]:';
  $tab[6] = '#'; // during provisionning prompt can change
  $index = 1;
  $result = '';
  for ($i = 1; ($i <= 10) && ($index !== 0); $i++)
  {
    try
    {
      $index = sendexpect_ex(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, $tab, 300000, true, true, true);
      $result .= $sendexpect_result;
    }
    catch (Exception $e)
    {
      sms_log_info(__FILE__.':'.__LINE__.": Connection with router was lost, try to reconnect\n");
      linux_generic_disconnect();
      $ret = linux_generic_connect();
      if ($ret != SMS_OK)
      {
        throw new SmsException("", ERR_SD_CONNREFUSED);
      }
      $index = 0;
    }
    switch ($index)
    {
      case 1:
        if (strpos($sendexpect_result, 'Dynamic mapping in use') !== false)
        {
          $cmd = "yes";
        }
        else if (strpos($sendexpect_result, 'Saving this config to nvram') !== false)
        {
          #% Warning: Saving this config to nvram may corrupt any network management or security files stored at the end of nvram.
          #encounter during restore on a device....
          $cmd = "yes";
        }
        else if (strpos($sendexpect_result, 'Dialplan-Patterns, Dialplans and Feature Servers on the system') !== false)
        {
          #This will remove all the existing DNs, Pools, Templates,
          #Dialplan-Patterns, Dialplans and Feature Servers on the system.
          #Are you sure you want to proceed? Yes/No? [no]:
          $cmd = "yes";
        }
        else
        {
          sms_log_error("$sdid:".__FILE__.':'.__LINE__.": [[!!! $sendexpect_result !!!]]\n");
          $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  '');
          save_result_file($result, "conf.error");
          throw new SmsException("$sendexpect_result", ERR_SD_CMDFAILED);
        }
        break;
      case 2:
        $cmd = '';
        break;
      case 3:
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  '');
        $cmd = '';
        break;
      case 4:
        $cmd = 'yes';
        break;
      case 5:
        $cmd = 'yes';
        break;
      case 6:
        extract_prompt();
        $index = 0;
        break;
      default:
        $index = 0;
        break;
    }
  } // loop while the router is asking questions

  return $result;
}

function func_reboot($msg = '')
{
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $result;

	$end = false;

	//Are you sure you wish to restart? (yes/cancel)
	//[cancel]: yes
	$tab[0] = '[cancel]:';
	$tab[1] = 'Restarting now...';
	$tab[2] = $sms_sd_ctx->getPrompt();

	$cmd_line = "restart $msg";

    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_line, $tab);

    if ($index === 0)
    {
      $cmd_line = "yes";
      sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_line, $tab[1]);
  	}
}
?>