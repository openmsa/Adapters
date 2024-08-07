<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('cisco_asa_generic', 'device_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported"
);

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
      device_disconnect();
      device_connect();
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
        $sms_sd_ctx->extract_prompt();
        $index = 0;
        break;
      default:
        $index = 0;
        break;
    }
  } // loop while the router is asking questions


  return $result;
}

function create_flash_dir($path)
{
  global $sms_sd_ctx;

  $root = dirname($path);
  if ($root !== '.')
  {
    // Create the dest directory
    create_flash_dir($root);
  }

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir $path", "#");
  if (($buffer === false) || (strpos($buffer, '%Error') !== false))
  {
    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = "]?";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "mkdir $path", $tab);
    if ($index === 1)
    {
      unset($tab);
      $tab[0] = $sms_sd_ctx->getPrompt();
      $tab[1] = "[confirm]";
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
      if ($index === 1)
      {
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", "#");
      }
    }
  }
  return SMS_OK;
}

function scp_to_router($src, $dst)
{	global $sms_sd_ctx;

	if (!empty($dst))
	{
		$dst_path = dirname($dst);
		if ($dst_path !== '.')
		{
			// Create the dest directory
			create_flash_dir($dst_path);
		}
	}

	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf t", "(config)#");
	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "ssh scopy enable", "(config)#");

	$passwd = "Xy" . mt_rand(10000, 99999) . "Y";
	$login = "NCO-SCP";

	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "username $login password $passwd privilege 15", "(config)#");

	device_disconnect(true);

	$net_profile = get_network_profile();
	$sd = &$net_profile->SD;
	$sd_ip_addr = $sd->SD_IP_CONFIG;
        $sd_mgt_port = $sd->SD_MANAGEMENT_PORT;

	$ret_scp = exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d /$dst -l $login -a $sd_ip_addr -p $passwd -P $sd_mgt_port", $output);

	device_connect();

	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf t", "(config)#");
	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "no ssh scopy enable", "(config)#");
	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "no username $login", "(config)#");
	sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", "#");

	if ($ret_scp !== SMS_OK)
	{
		return $ret_scp;
	}

	// Check file size
	$ret = check_file_size($src, $dst);
	if ($ret !== SMS_OK)
	{
		return $ret;
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
	}

	return ERR_SD_SCP;
}

function check_file_size($src, $dst)
{
  global $sms_sd_ctx;

  $filename = basename($src);
  $orig_size = filesize($src);
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir $dst", "#");
  if (preg_match("@^\s+\S+\s+\S+\s+(?<size>\d+)\s+.*\s+{$filename}\s*$@m", $buffer, $matches) > 0)
  {
    $size = $matches['size'];
    if ($size != $orig_size)
    {
      // remove bad file
      unset($tab);
      $tab[0] = '#';
      $tab[1] = ']?';
      $tab[2] = '[confirm]';
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete $dst", $tab);
      while ($index > 0)
      {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
      }
      sms_log_error("transfering $src failed: size $size found $orig_size awaited");
      throw new SmsException("transfering $src failed: size $size found $orig_size awaited", ERR_SD_FILE_TRANSFER);
    }
  }
  else
  {
    sms_log_error("transfering $src failed: file not found on device");
    throw new SmsException("transfering $src failed: file not found on device", ERR_SD_FILE_TRANSFER);
  }
  return SMS_OK;
}

function tftp_to_router($src, $dst)
{
  global $sms_sd_ctx;
  global $error_found;
  global $is_local_file_server;
  global $file_server_addr;

  $dst_path = dirname($dst);
  if ($dst_path !== '.')
  {
    // Create the dest directory
    create_flash_dir($dst_path);
  }

  if ($is_local_file_server)
  {
    $tftp_server_addr = $file_server_addr;
  }
  else
  {
    $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
  }

  unset($tab);
  $tab[0] = $sms_sd_ctx->getPrompt();
  $tab[1] = '[confirm]';
  $tab[2] = ']?';
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$tftp_server_addr/$src flash:/$dst", $tab, 14400000);
  while ($index > 0)
  {
    if ($error_found)
    {
      echo "transfering $src failed: TFTP error found\n";
      throw new SmsException("transfering $src failed: TFTP error found", ERR_SD_TFTP);
    }
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, 14400000);
  }
  if ($error_found)
  {
    echo "transfering $src failed: TFTP error found\n";
    throw new SmsException("transfering $src failed: TFTP error found", ERR_SD_TFTP);
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
  check_file_size($src, $dst);

  return SMS_OK;
}
function extract_to_router($src, $dst, $sdid)
{
  global $sms_sd_ctx;
  global $error_found;
  global $is_local_file_server;
  global $file_server_addr;

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

    $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
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
function send_file_to_router($src, $dst)
{
  global $is_local_file_server;

  echo "Sending $src to $dst\n";

  if (!$is_local_file_server)
  {
    // try SCP
    try
    {
      scp_to_router($src, $dst);
    }
    catch (Exception | Error $e)
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

      tftp_to_router($src, $dst);

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

function func_reboot($msg = 'SMSEXEC')
{
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $result;

  $end = false;
  $tab[0] = '[yes/no]:';
  $tab[1] = '[confirm]';
  $tab[2] = 'System config has been modified. Save? [Y]es/[N]o:';
  $tab[3] = 'terminals';
  $tab[4] = $sms_sd_ctx->getPrompt();
  $cmd_line = "reload in 001 reason $msg";
  do
  {
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_line, $tab);
    switch ($index)
    {
      case 0:
        $cmd_line = 'no';
        break;
      case 1:
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
        break;
      case 2:
        $cmd_line = 'Y';
        break;
      case 3:
        $cmd_line = '';
        break;
      case 4:
        $end = true;
        break;
    }
  }
  while(!$end);
}


function func_write()
{
  global $sms_sd_ctx;
  global $sendexpect_result;

  unset($tab);
  $tab[0] = "[no]:";
  $tab[1] = "[confirm]";
  $tab[2] = $sms_sd_ctx->getPrompt();
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "write", $tab);
  if ($index === 0)
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $sendexpect_result !!!]]\n");
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");
    throw new SmsException($sendexpect_result, ERR_SD_CMDFAILED);
  }

  if ($index === 1)
  {
    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");
  }
  return SMS_OK;
}

?>
