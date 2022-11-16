<?php

require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';
require_once load_once('smsbd', 'common.php');
require_once load_once('cisco_nx_rest', 'me_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported");

// extract the prompt
function extract_prompt()
{
  global $sms_sd_ctx;

  /* pour se synchroniser et extraire le prompt correctement */
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'conf t', '(config)#');
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit', '#');
  $buffer = trim($buffer);
  $buffer = substr(strrchr($buffer, "\n"), 1); // recuperer la derniere ligne
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
      $index = sendexpect_ex(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd, $tab, 300000, true, true, true);
      $result .= $sendexpect_result;
    }
    catch (Exception | Error $e)
    {
      sms_log_info(__FILE__ . ':' . __LINE__ . ": Connection with router was lost, try to reconnect\n");
      me_cli_disconnect();
      $ret = me_cli_connect();
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
          // % Warning: Saving this config to nvram may corrupt any network management or security files stored at the end of nvram.
          // encounter during restore on a device....
          $cmd = "yes";
        }
        else if (strpos($sendexpect_result, 'Dialplan-Patterns, Dialplans and Feature Servers on the system') !== false)
        {
          // This will remove all the existing DNs, Pools, Templates,
          // Dialplan-Patterns, Dialplans and Feature Servers on the system.
          // Are you sure you want to proceed? Yes/No? [no]:
          $cmd = "yes";
        }
        else
        {
          sms_log_error("$sdid:" . __FILE__ . ':' . __LINE__ . ": [[!!! $sendexpect_result !!!]]\n");
          $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
          save_result_file($result, "conf.error");
          throw new SmsException("$sendexpect_result", ERR_SD_CMDFAILED);
        }
        break;
      case 2:
        $cmd = '';
        break;
      case 3:
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
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

function scp_to_router($src, $dst)
{
  global $sms_sd_ctx;

  if (!empty($dst))
  {
    $dst_path = dirname($dst);
  }

  me_cli_disconnect();

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;
  $sd_ip_addr = $sd->SD_IP_CONFIG;
  $login = $sd->SD_LOGIN_ENTRY;
  $passwd = $sd->SD_PASSWD_ENTRY;

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l $login -a $sd_ip_addr -p $passwd", $output);

  $ret = me_cli_connect();

  if ($ret !== SMS_OK)
  {
    if ($ret_scp !== SMS_OK)
    {
      throw new SmsException("Sending file $src Failed ($out)", $ret_scp);
    }
    throw new SmsException("connection failed to the device while tranfering file $src", $ret);
  }

  $out = '';
  foreach ($output as $line)
  {
    $out .= "$line\n";
  }

  if ($ret_scp !== SMS_OK)
  {
    throw new SmsException("Sending file $src Failed ($out)", $ret_scp);
  }

  // Check file size
  check_file_size($src, $dst, true);

  if (strpos($out, 'SMS-CMD-OK') !== false)
  {
    return SMS_OK;
  }

  foreach ($output as $line)
  {
    sms_log_error($line);
  }

  throw new SmsException("Sending file $src Failed ($out)", $ret);
}

function check_file_size($local_file, $remote_file, $remove_remote_file = true)
{
  global $sms_sd_ctx;

  $filename = basename($local_file);
  $orig_size = filesize($local_file);
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir $remote_file");
  if (preg_match("@^\s+(?<size>\d+)\s+.*\s+{$filename}\s*$@m", $buffer, $matches) > 0)
  {
    $size = $matches['size'];
    if ($size != $orig_size)
    {
      if ($remove_remote_file)
      {
        // remove bad file
        unset($tab);
        $tab[0] = '#';
        $tab[1] = ']?';
        $tab[2] = '[confirm]';
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete $remote_file", $tab);
        while ($index > 0)
        {
          $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
        }
        sms_log_error("transfering $local_file failed: size $size found $orig_size awaited");
        throw new SmsException("transfering $local_file failed: size $size found $orig_size awaited", ERR_SD_FILE_TRANSFER);
      }
      else
      {
        unlink($local_file);
        sms_log_error("transfering $remote_file failed: size $size found $orig_size awaited");
        throw new SmsException("transfering $remote_file failed: size $size found $orig_size awaited", ERR_SD_FILE_TRANSFER);
      }
    }
  }
  else
  {
    if ($remove_remote_file)
    {
      sms_log_error("transfering $local_file failed: file not found on device");
      throw new SmsException("transfering $local_file failed: file not found on device", ERR_SD_FILE_TRANSFER);
    }
    else
    {
      sms_log_error("transfering $remote_file failed: file not found on device");
      throw new SmsException("transfering $remote_file failed: file not found on device", ERR_SD_FILE_TRANSFER);
    }
  }
  return SMS_OK;
}

?>
