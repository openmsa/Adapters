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
  if ($root !== '.')
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
    $dst_path = dirname($dst);
    if ($dst_path !== '.')
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

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst_disk:/$dst -l $login -a $sd_ip_addr -p $passwd", $output);

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

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "cd $dst_disk:");
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir | include Directory");
  if (preg_match("@^Directory\s+of\s+(?<disk_unix_path>\S+)\s*$@m", $buffer, $matches) > 0)
  {
    $disk_unix_path = $matches['disk_unix_path'];

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "bash stat -c '%s' $disk_unix_path/$filename");
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
          $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete /noprompt $dst_disk:$remote_file", $tab);
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

function tftp_to_router($src, $dst, $tftp_server = null, $erase_flash = false)
{
  global $sms_sd_ctx;
  global $error_found;
  global $is_local_file_server;
  global $file_server_addr;
  global $disk_names;
  $dst_disk = "disk0";
  $file = basename($src);

  init_local_file_server();

  foreach ($disk_names as $disk_name)
  {
    if (preg_match($disk_name, $src, $match) > 0)
    {
      $dst_disk = $match[0];
      break;
    }
  }

  $dst_path = dirname($dst);
  if ($dst_path !== '.')
  {
    // Create the dest directory
    create_flash_dir($dst_path, $dst_disk);
  }

  if (!empty($tftp_server))
  {
    $tftp_server_addr = $tftp_server;
  }
  else if ($is_local_file_server)
  {
    $tftp_server_addr = $file_server_addr;
  }
  else
  {
    $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
  }

  unset($tab);
  $tab[0] = 'ocket error';
  $tab[1] = 'imed out';
  $tab[2] = 'nvalid IP address or hostname';
  $tab[3] = 'o such file or directory';
  $tab[4] = $sms_sd_ctx->getPrompt();
  $tab[5] = 'Erase flash:';
  $tab[6] = '[confirm]';
  $tab[7] = ']?';

  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$tftp_server_addr/$src $dst_disk:/$dst", $tab, 14400000);
  while ($index !== 4)
  {
    if ($error_found || $index <= 3)
    {
      echo "transfering $file failed: TFTP error found\n";
      throw new SmsException("transfering $file failed: TFTP error found", ERR_SD_TFTP);
    }
    if ($index === 5 && ($erase_flash === false))
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "n", $tab, 14400000);
    }
    else
    {
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, 14400000);
    }
  }
  if ($error_found || $index <= 3)
  {
    echo "transfering $file failed: TFTP error found\n";
    throw new SmsException("transfering $file failed: TFTP error found", ERR_SD_TFTP);
  }

  if ($is_local_file_server)
  {
    // Compare to the SOC file
    $src = "{$_SERVER['FMC_REPOSITORY']}/$src";
  }
  else
  {
    $src = "{$_SERVER['TFTP_BASE']}/$src";
  }

  // Check file size
  if (empty($tftp_server))
  {
    check_file_size($src, $dst, true, $dst_disk);
  }

  return SMS_OK;
}

function extract_to_router($src, $dst, $sdid)
{
  global $sms_sd_ctx;
  global $error_found;
  global $is_local_file_server;
  global $file_server_addr;

  init_local_file_server();

  $fileinfo = pathinfo($src);
  $file = $fileinfo['basename'];
  $dst_extract = explode("/", $dst);
  $dst = $dst_extract[0];

  if ($is_local_file_server)
  {
    $tftp_server_addr = $file_server_addr;
    $src_path = $src;
  }
  else
  {
    $tftp_dir = "{$_SERVER['TFTP_BASE']}/$sdid/CME";

    // Copy the file to TFTP server
    if (!is_dir($tftp_dir))
    {
      mkdir_recursive($tftp_dir, 0775);
    }
    $cmd = "cp $src $tftp_dir";
    $ret = exec_local(__FILE__, $cmd, $output);
    echo "RET $ret \n";

    $net_profile = get_network_profile();
    $sd = &$net_profile->SD;
    $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
    if($sd->SD_CONF_ISIPV6)
    {
    	$tftp_server_addr = $_SERVER['SMS_ADDRESS_IPV6'];
    }
    $src_path = "$sdid/CME/$file";
  }

  unset($tab);
  $tab[0] = "(Time out)";
  $tab[1] = "(Timed out)";
  $tab[2] = "[time out]";
  $tab[3] = "checksum error";
  $tab[4] = "No such file";
  $tab[5] = $sms_sd_ctx->getPrompt();
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "archive tar /xtract tftp://$tftp_server_addr/$src_path $dst:", $tab, 36000000);

  if (!$is_local_file_server)
  {
    // Remove TFTP file
    $cmd = "rm -f $tftp_dir/$file";
    $ret = exec_local(__FILE__, $cmd, $output);
  }

  if ($index < 4)
  {
    throw new SmsException($sendexpect_result, ERR_SD_TFTP);
  }
  if ($index === 4)
  {
    throw new SmsException($sendexpect_result, ERR_LOCAL_FILE);
  }

  return SMS_OK;
}

function send_file_to_router($src, $dst, $tftp_server)
{
  global $is_local_file_server;
  global $sms_sd_ctx;
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  init_local_file_server();

  echo "Sending $src to $dst via $protocol\n";

  if (!$is_local_file_server)
  {
  	if ($protocol === 'SSH'){
  		$ret = scp_to_router($src, $dst);
  	}

  	if ($ret !== SMS_OK || $protocol === 'TELNET')
  	{
    	// copy the file to the ftp server if needed
    	if (strpos($src, "{$_SERVER['TFTP_BASE']}/") !== 0)
    	{
     	 $filename = basename($src);
     	 $tmp_dir = "{$_SERVER['TFTP_BASE']}/tmp_" . rand(100000, 999999);
     	 mkdir($tmp_dir, 0755);
    	  copy($src, "$tmp_dir/$filename");
     	 $src = "$tmp_dir/$filename";
     	 $tmp_file_used = true;
    	}
    	else
    	{
    	  $tmp_file_used = false;
    	}
    	// Try TFTP
    	$src = str_replace("{$_SERVER['TFTP_BASE']}/", '', $src);

    	tftp_to_router($src, $dst, $tftp_server);

    	if ($tmp_file_used)
    	{
    	  rmdir_recursive($tmp_dir);
    	}
  	}
  }
  else
  {
    // Use only tftp on local server
    // strip /opt/fmc_repository if necessary
    $pos = strpos($src, "{$_SERVER['FMC_REPOSITORY']}/");
    if ($pos !== false)
    {
      $src = substr($src, strpos($src, "{$_SERVER['FMC_REPOSITORY']}/") + strlen("{$_SERVER['FMC_REPOSITORY']}/"));
    }
    tftp_to_router($src, $dst);
  }

  return SMS_OK;
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
