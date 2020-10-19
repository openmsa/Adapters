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
require_once load_once('adtran_generic', 'common.php');
require_once load_once('adtran_generic', 'apply_errors.php');
require_once load_once('adtran_generic', 'adtran_generic_configuration.php');

require_once "$db_objects";

define('DELAY', 200000);
function adtran_generic_apply_conf($configuration, $push_to_startup = false)
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
  $line_config_mode = sms_sd_get_config_mode_from_sdinfo($sms_sd_info);
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  $file_name = "{$sdid}.cfg";
  $full_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;

  $ret = save_file($configuration, $full_name);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  // ---------------------------------------------------
  // SCP mode configuration (default mode)
  // ---------------------------------------------------
  $ret = SMS_OK;
  /*if ($protocol === 'SSH')
  {
    echo "SCP mode configuration\n";

    try
    {
      $ret = scp_to_router($full_name, $file_name);
      if ($ret === SMS_OK)
      {
        // SCP OK
        if ($push_to_startup)
        {
          $SMS_OUTPUT_BUF = copy_to_running("copy flash:$file_name startup-config");
          save_result_file($SMS_OUTPUT_BUF, "conf.error");
        }
        else
        {
          $SMS_OUTPUT_BUF = copy_to_running("copy flash:$file_name running-config");
          save_result_file($SMS_OUTPUT_BUF, "conf.error");
        }
        $SMS_OUTPUT_BUF = preg_replace("~[\r\n]~", "", $SMS_OUTPUT_BUF);
        foreach ($apply_errors as $apply_error)
        {
          if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
          {
            sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
            $ret = ERR_SD_CMDFAILED;
            break;
          }
        }

        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete flash:$file_name", "]?");
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", "[confirm]");
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");

        if ($ret === SMS_OK)
        {
          if (!$push_to_startup)
          {
            $ret = func_write();
          }
        }
        return $ret;
      }
    }
    catch (Exception | Error $e)
    {
      if (strpos($e->getMessage(), 'connection failed') !== false)
      {
        return ERR_SD_CONNREFUSED;
      }
      sms_log_error(__FILE__ . ':' . __LINE__ . ":SCP Error $ret\n");
    }
  }*/

  // ---------------------------------------------------
  // Line by line mode configuration  - Used for ZTD port console
  // ---------------------------------------------------
  $ret = SMS_OK;
  //if ($line_config_mode === 1)
  if ($protocol === 'SSH')
  {
    echo "Line by line mode configuration\n";
    $ERROR_BUFFER = '';

    // GET FIRMWARE VERSION WITH CONFIGURATION VARIABLES
    get_asset();
    $bin_config_var = $SD->SD_CONFIGVAR_list['FIRMWARE']->VAR_VALUE;
    if (!empty($bin_config_var))
    {
      manage_firmware_port_console($SD);
    }

    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf t", "(config)#", DELAY);

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
            $SMS_OUTPUT_BUF = '';
          }
        }
      }
      $line = get_one_line($buffer);
    }

    // Refetch the prompt cause it can change during the apply conf
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', '#');
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'conf t', '(config)#');
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
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
      return ERR_SD_CMDFAILED;
    }
    else
    {
      save_result_file("No error found during the application of the configuration", "conf.error");
    }

    set_serial_and_hostname_in_db($SD);
    $ret = func_write();

    return $ret;
  }

	if($SD->SD_CONF_ISIPV6)
	{
		$sms_ip_addr = $_SERVER['SMS_ADDRESS_IPV6'];
	}

  // ---------------------------------------------------
  // TFTP mode configuration
  // NORMAL MODE : Copy config to running-conf + catch conf.error + write
  // ZTD MODE : Copy config to startup
  // ---------------------------------------------------
  echo "TFTP mode configuration\n";
  $ret = SMS_OK;
  $sms_ip_addr = $_SERVER['SMS_ADDRESS_IP'];

  $is_ztd = false;
  if ($sms_sd_ctx->getIpAddress() !== $SD->SD_IP_CONFIG)
  {
    $is_ztd = true;
  }

  if ($is_ztd || $push_to_startup)
  {
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/$file_name startup-config", "]?");
    $SMS_OUTPUT_BUF = copy_to_running('');
  }
  else
  {
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/$file_name running-config", "]?");

    $SMS_OUTPUT_BUF = copy_to_running('');
    save_result_file($SMS_OUTPUT_BUF, "conf.error");

    foreach ($apply_errors as $apply_error)
    {
      if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
      {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
        return ERR_SD_CMDFAILED;
      }
    }
  }

  if (!strpos($SMS_OUTPUT_BUF, 'bytes copied'))
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ":tftp transfer failed\n");
    return ERR_SD_TFTP;
  }

  if (!$is_ztd || !$push_to_startup)
  {
    $ret = func_write();
  }
  $SMS_OUTPUT_BUF = preg_replace("~[\r\n]~", "", $SMS_OUTPUT_BUF);
  return $ret;
}

?>