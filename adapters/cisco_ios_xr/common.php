<?php

require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';
require_once load_once('smsbd', 'common.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported");

$disk_names = array(
    "@flash[0-9]+@",
    "@diskboot@",
    "@bootflash@",
    "@flash@");

// extract the prompt
function extract_prompt()
{
  global $sms_sd_ctx;

  /* pour se synchroniser et extraire le prompt correctement */
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'conf t', '(config)#');
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit', '#');
  $buffer = trim($buffer);
  $buffer = substr(strrchr($buffer, "\n"), 1); // recuperer la derniere ligne
  $sms_sd_ctx->setPrompt($buffer);
}

function enter_config_mode()
{
  global $sms_sd_ctx;

  unset($tab);
  $tab[0] = "try later";
  $tab[1] = "(config)#";

  $prompt_state = 0;
  $index = 99;
  $timeout = 2000;

  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'conf t');

  for ($i = 1; ($i <= 5) && ($prompt_state < 2); $i++)
  {
    $timeout = $timeout * 2;

    switch ($index)
    {
      case -1: // Error
        cisco_ios_xr_disconnect();
        return ERR_SD_TIMEOUTCONNECT;

      case 99: // wait for router
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
        break;

      case 0: // "try later"
        sleep($timeout);
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'conf t');

        $index = 99;
        $prompt_state = 1;
        break;

      case 1: // "(config)#"
        $prompt_state = 2;
        break;
    }
  }
  if ($prompt_state !== 2)
  {
    return ERR_SD_CMDTMOUT;
  }

  return SMS_OK;
}


function get_asset()
{
  global $sms_sd_ctx;

  $show_ver_asset_patterns = array(
      'serial' => '@Processor board ID (?<serial>\S*)@',
      'bin' => '@flash:(?<bin>\S*)"@');

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version", $sms_sd_ctx->getPrompt(), DELAY);
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    foreach ($show_ver_asset_patterns as $name => $pattern)
    {
      if (preg_match($pattern, $line, $matches) > 0)
      {
        $sms_sd_ctx->setParam($name, trim($matches[$name]));
      }
    }
    $line = get_one_line($buffer);
  }
}

?>
