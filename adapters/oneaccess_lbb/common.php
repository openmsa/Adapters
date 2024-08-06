<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');

$error_list[] = "Error";
$error_list[] = "ERROR";
$error_list[] = "Duplicate";
$error_list[] = "Invalid";
$error_list[] = "Unsupported";

// function to be called after the configuration transfer
function copy_to_running($cmd)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;

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
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, $tab, 500000);
    $result .= $sendexpect_result;
    switch ($index)
    {
      case 1:
        if (strpos($sendexpect_result, 'Dynamic mapping in use') !== false)
        {
          $cmd = "yes";
        } elseif(strpos($sendexpect_result, 'Saving this config to nvram') !== false) {
          #% Warning: Saving this config to nvram may corrupt any network management or security files stored at the end of nvram.
          #encounter during restore on a device....
          $cmd = "yes";
        }
        else
        {
          sms_log_error("$sdid:".__FILE__.':'.__LINE__.": [[!!! $sendexpect_result !!!]]\n");
          $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, '');
          save_result_file($result, "conf.error");
          return ERR_SD_CMDFAILED;
        }
        break;
      case 2:
        $cmd = '';
        break;
      case 3:
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, '');
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

function tftp_to_router($src, $dst)
{
  global $sms_sd_ctx;
  global $error_found;
  global $is_local_file_server;
  global $file_server_addr;

  init_local_file_server();

  if ($is_local_file_server)
  {
    $tftp_server_addr = $file_server_addr;
  }
  else
  {
    $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
  }

  unset($tab);
  $tab[0] =  $sms_sd_ctx->getPrompt();
  $tab[1] = '[confirm]';
  $tab[2] = ']?';

  unset($error_list);
  $error_list[] = "file not found";
  $error_list[] = "Illegal software format";
  $error_list[] = "Abort from tftp server";

  $sms_sd_ctx->setParam('error_list', $error_list);

  $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "copy tftp://$tftp_server_addr/$src $dst", $tab,14400000);

if ($index === false)
{
   echo "TFTP error found\n";
   return ERR_SD_TFTP;
}

  while ($index > 0) {
    if ($error_found)
    {
      echo "TFTP error found\n";
      return ERR_SD_TFTP;
    }

    $index = sendexpect(__FILE__.':'.__LINE__,$sms_sd_ctx, "", $tab,14400000);
  }

  if ($error_found)
  {
    echo "TFTP error found\n";
    return ERR_SD_TFTP;
  }

  return SMS_OK;
}


function send_file_to_router($src, $dst)
{
  global $is_local_file_server;
  global $sms_sd_ctx;
  $protocol = $sms_sd_ctx->getParam('PROTOCOL');

  init_local_file_server();

  echo "Sending $src to $dst\n";

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
	    	$tmp_dir = "{$_SERVER['TFTP_BASE']}/tmp_".rand(100000, 999999);
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
	    $ret = tftp_to_router($src, $dst);

	    if ($tmp_file_used)
	    {
	    	rmdir_recursive($tmp_dir);
	    }
  	}

  }
  else
  {
    // Use only tftp on local server
    $ret = tftp_to_router($src, $dst);
  }
  return $ret;
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

	unset($tab);
  $tab[0] = "Error";
  $tab[1] = $sms_sd_ctx->getPrompt();
  $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "untar $file /$dst clean-up all-sub-dir", $tab, 36000000);

  if (!$is_local_file_server)
  {
  	// Remove TFTP file
  	$cmd = "rm $file";
  	$ret = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd, ">");
  }

  if ($index === 0)
  {
  	throw new SmsException($sendexpect_result, ERR_LOCAL_FILE);
  }

  return SMS_OK;
}


function scp_to_router($src, $dst)
{
	global $sms_sd_ctx;
	/* global $disk_names;
	$dst_disk = "flash";

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

	$login = $_SERVER['SCP_USERNAME'];
	$passwd = $_SERVER['SCP_PASSWORD'];

	if (!empty($login) || !empty($passwd))
	{
		$passwd = activate_scp($login, $passwd);
	}
	else{
		$login = "NCO-SCP";
		$passwd = activate_scp($login);
	} */

	oneaccess_lbb_disconnect();

	$net_profile = get_network_profile();
	$sd = &$net_profile->SD;
	$sd_ip_addr = $sd->SD_IP_CONFIG;
	$login = $sd->SD_LOGIN_ENTRY;
	$passwd = $sd->SD_PASSWD_ENTRY;
        $sd_mgt_port = $sd->SD_MANAGEMENT_PORT;

	/* //rename filename with smaller size - to scp file name should be less than 40 characters.

	$src_file = basename($src);
	$src_file_name_length = strlen($src_file);

	if($src_file_name_length > 40)
	{
		$ret_move = exec_local(__FILE__ . ':' . __LINE__, "mv $src_file ONEOS.ZZZ", $output);
	} */

	$ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d /$dst -l $login -a $sd_ip_addr -p '$passwd' -P $sd_mgt_port", $output);

	if ($ret_scp !== SMS_OK)
	{
		throw new SmsException("Sending file $src Failed ($out)", $ret_scp);
	}

	$ret = oneaccess_lbb_connect();

	if ($ret !== SMS_OK)
	{
		throw new SmsException("connection failed to the device while tranfering file $src", $ret);
	}

	//deactivate_scp($login);

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
	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "ls /$remote_file");
	if (preg_match("/{$filename}\s+(?<size>\d+)/", $buffer, $matches) > 0)
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
				$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "rm /$remote_file", $tab);
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

function func_reboot()
{
	global $sms_sd_ctx;
	unset($tab);
	$tab[0] = "Resource is temporarily unavailable - try again later";
	$tab[1] = "configuration";
	$tab[2] = "reboot";

	$choice = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "reboot", $tab);

	while($choice !== -1)
	{
		switch ($choice)
		{
			case 0:
				sleep(2);
				$choice = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "reboot", $tab);
				break;
			case  1:
				$choice = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "Y", $tab);
				break;
			case  2:
				$sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "Y");
				$choice = -1;
				echo "Device reboot ongoing\n";
				break;
			default:
				throw new SmsException("Failed to reboot device!", ERR_SD_CMDFAILED);
		}
	}
}
?>
