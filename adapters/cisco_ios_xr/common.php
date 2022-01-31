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
    "@apphost@",
    "@config@",
    "@disk[0-9]+@",
    "@harddisk@",
    "@rootfs@",
    "@flash@");

$default_disk = "disk0";

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

function create_flash_dir($path, $dst_disk)
{
  global $sms_sd_ctx;
  global $sendexpect_result;

  $root = dirname($path);
  if (($root !== '.') && ($root !== '/'))
  {
    // Create the dest directory
    create_flash_dir($root, $dst_disk);
  }

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir $dst_disk:$path");
  if (($buffer === false) || (strpos($buffer, '%Error') !== false))
  {
    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = "]?";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "mkdir $dst_disk:$path", $tab);
    if ($index === 1)
    {
      unset($tab);
      $tab[0] = $sms_sd_ctx->getPrompt();
      $tab[1] = "[confirm]";
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
      if ($index === 1)
      {
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");
      }
    }
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir $dst_disk:$path");
    if (($buffer === false) || (strpos($buffer, '%Error') !== false))
    {
      throw new SmsException("creating directory '$path' is not supported by the device.\nTry formatting the flash disk with the command:\n'format flash:'", ERR_SD_FILE_TRANSFER);
    }
  }
  return SMS_OK;
}

function scp_from_router($src, $dst)
{
  global $sms_sd_ctx;
  global $status_message;

  cisco_ios_xr_disconnect(true);

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;
  $sd_ip_addr = $sd->SD_IP_CONFIG;
  $login      = $sd->SD_LOGIN_ENTRY;
  $passwd     = $sd->SD_PASSWD_ENTRY;

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -r -s $src -d $dst -l $login -a $sd_ip_addr -p $passwd", $output);

  sleep(1); // to let the device finish scp execution

  $ret = cisco_ios_xr_connect();
  if ($ret !== SMS_OK)
  {
    if ($ret_scp !== SMS_OK)
    {
      throw new SmsException("Getting file $src Failed", $ret_scp);
    }
    throw new SmsException("Connection Failed while getting file $src", $ret);
  }

  if ($ret_scp !== SMS_OK)
  {
    throw new SmsException("Getting file $src Failed", $ret_scp);
  }

  foreach ($output as $line)
  {
    if (strpos($line, 'SMS-CMD-OK') !== false)
    {
      return SMS_OK;
    }
  }

  foreach ($output as $line)
  {
    sms_log_error($line);
    $status_message .= "{$line}\n | ";
  }

  throw new SmsException("Getting file $src Failed", ERR_SD_SCP);
}

function scp_to_router($src, $dst)
{
  global $sms_sd_ctx;
  global $disk_names;
  global $default_disk;
  $dst_disk = $default_disk;

  foreach ($disk_names as $disk_name)
  {
    if (preg_match($disk_name, $src, $match) > 0)
    {
      $dst_disk = $match[0];
      break;
    }
  }

  if (!empty($dst))
  {
    // $src = /opt/sms/spool/tftp/UBI153.cfg ; $dst = disk0:/write_UBI153.cfg ; $dst_disk = disk0 ; $dst_filepath = /write_UBI153.cfg
    $dst_filepath = str_replace("$dst_disk:", '', $dst);
    $dst_path = dirname($dst_filepath);

    if (($dst_path !== '.') && ($dst_path !== '/'))
    {
      // Create the dest directory
      create_flash_dir($dst_path, $dst_disk);
    }
  }

  cisco_ios_xr_disconnect();

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;
  $sd_ip_addr = $sd->SD_IP_CONFIG;
  $login      = $sd->SD_LOGIN_ENTRY;
  $passwd     = $sd->SD_PASSWD_ENTRY;

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l $login -a $sd_ip_addr -p $passwd", $output);

  sleep(1); // to let the device finish scp execution

  $ret = cisco_ios_xr_connect();

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
  check_file_size($src, $dst, true, $dst_disk);

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

function check_file_size($local_file, $remote_file, $remove_remote_file = true, $dst_disk = "disk0")
{
  global $sms_sd_ctx;
  /*
    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000#dir | include Directory
    Fri Nov 26 17:17:44.752 UTC
    Directory of /misc/scratch
    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000#bash stat -c '%s' /misc/scratch/UBI153.cfg
    Fri Nov 26 17:18:21.593 UTC
    1506
   */
  $filename = basename($local_file);
  $orig_size = filesize($local_file);

  $remote_filename = str_replace("$dst_disk:", '', $remote_file);

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "cd $dst_disk:");
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir | include Directory");
  if (preg_match("@^Directory\s+of\s+(?<disk_unix_path>\S+)\s*$@m", $buffer, $matches) > 0)
  {
    $disk_unix_path = $matches['disk_unix_path'];

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "bash stat -c '%s' $disk_unix_path/$remote_filename");
    if (preg_match("@^\s*(?<size>\d+)\s*$@m", $buffer, $matches) > 0)
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
          $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete /noprompt $remote_file", $tab);
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
  }
  else
  {
    sms_log_error("cannot read default remote directory (disk0)");
    throw new SmsException("transfering $remote_file failed: cannot read default remote directory (disk0)", ERR_SD_FILE_TRANSFER);
  }
  return SMS_OK;
}

function send_file_to_router($src, $dst)
{
  global $sms_sd_ctx;
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  if ($protocol !== 'SSH')
  {
      echo "Sending $src to $dst via SSH, $protocol no more supported\n";
  }
  else
  {
    echo "Sending $src to $dst via $protocol\n";
  }

  $ret = scp_to_router($src, $dst);
  return $ret;
}

function send_all_files($dir, $dst_path = "")
{
  if (!empty($dst_path) && (strrpos($dst_path, '/') !== (strlen($dst_path) - 1)))
  {
    $dst_path = "{$dst_path}/";
  }

  if ($handle = opendir($dir))
  {
    while (false !== ($file = readdir($handle)))
    {
      // ignore .* files (includes . and ..)
      if (strpos($file, ".") !== 0)
      {
        if (is_dir("$dir/$file"))
        {
          // reproducce the directory tree on the destination
          send_all_files("$dir/$file", "{$dst_path}{$file}");
        }
        else
        {
          send_file_to_router("$dir/$file", "{$dst_path}{$file}");
        }
      }
    }
    closedir($handle);
  }

  return SMS_OK;
}

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

  $cmd_line = "reload location all";

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


function delete_force_flash()
{
  global $sms_sd_ctx;

  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = "]?";

  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete /noprompt flash:*", $tab);
  if ($index === 1)
  {
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $sms_sd_ctx->getPrompt());
  }
  return SMS_OK;
}

function get_asset()
{
  global $sms_sd_ctx;

  $name = 'serial';
  $pattern = '@\s*PID:\s*\S*\s*VID:\s*\S*\s*SN:\s*(?<serial>\S*)@';
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "admin show inv chassis | include SN:", $sms_sd_ctx->getPrompt(), DELAY);
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    if (preg_match($pattern, $line, $matches) > 0)
    {
      $sms_sd_ctx->setParam($name, trim($matches[$name]));
      break;
    }
    $line = get_one_line($buffer);
  }

  $name = 'bin';
  $pattern = '@\s*Version\s*:\s*(?<bin>\S*)@';
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version | include Version", $sms_sd_ctx->getPrompt(), DELAY);
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    if (preg_match($pattern, $line, $matches) > 0)
    {
      $sms_sd_ctx->setParam($name, trim($matches[$name]));
      break;
    }
    $line = get_one_line($buffer);
  }

}

?>
