<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';
require_once load_once('fujitsu_ipcom', 'fujitsu_ipcom_connect.php');

function func_reboot($msg = 'SMSEXEC', $reload_now = false, $is_port_console = false)
{
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $result;

  $end = false;
  $tab[0] = '[yes/no]:';
  $tab[1] = '[confirm]';
  $tab[2] = 'to enter the initial configuration dialog? [yes/no]';
  $tab[3] = 'RETURN to get started!';
  $tab[4] = $sms_sd_ctx->getPrompt();
  $tab[5] = '>';
  $tab[6] = 'rommon 1';
  if ($reload_now !== false)
  {
    $cmd_line = "reload";
  }
  else
  {
    $cmd_line = "reload in 001 reason $msg";
  }

  do
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
    if ($index === 0)
    {
      $cmd_line = 'no';
    }
    else if ($index === 1)
    {
      if ($cmd_line === 'no')
      {
        // enlever l'echo
        $result .= substr($sendexpect_result, 3);
      }
      else
      {
        $result .= $sendexpect_result;
      }
      $cmd_line = '';
    }
    else if ($index === 2)
    {
      $cmd_line = 'no';
    }
    else if ($index === 3)
    {
      if ($is_port_console === false)
      {
        $cmd_line = '';
      }
      else
      {
        $cmd_line = "\r";
      }
    }
    else if ($index === 6)
    {
      throw new SmsException("Rommon mode after reloading", ERR_SD_FAILED);
    }
    else
    {
      $end = true;
    }
  } while (!$end);
}

?>
