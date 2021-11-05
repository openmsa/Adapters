<?php
/*
 * Date : Oct 19, 2007
*/

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('terraform_generic', 'terraform_generic_connect.php');

$disk_names = array(
    "@flash[0-9]+@",
    "@diskboot@",
    "@bootflash@",
    "@flash@");
    
    
    
function func_reboot($msg = '')
{
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $result;

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


function create_flash_dir($path)
{
  global $sms_sd_ctx;
  global $sendexpect_result;

  $root = dirname($path);
  if (($root !== '.')&&($root != '\/')&&($root != '/'))
  {
    // Create the dest directory
    create_flash_dir($root);
  }
  sms_log_error("  Create_flash_dir run: dir -1 $path");
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir -1 $path");
  sms_log_error(" Create_flash_dir result buffer=$buffer;" );
  if (($buffer === false) || (strpos($buffer, 'No such file') !== false))
  {
    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = "]?";
    sms_log_error("  Create_flash_dir will run: mkdir $path" );
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "mkdir $path", $tab);
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
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir -1 $path");
    if (($buffer === false) || (strpos($buffer, 'No such file') !== false))
    {
      throw new SmsException("creating directory '$path' is not supported by the device.", ERR_SD_FILE_TRANSFER);
    }
  }
  return SMS_OK;
}

function scp_from_router($src, $dst)
{
  global $sms_sd_ctx;
  global $status_message;

  terraform_generic_disconnect(true);

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;
  $sd_ip_addr = $sd->SD_IP_CONFIG;

  $login = $sd->SD_LOGIN_ENTRY;
  $passwd = $sd->SD_PASSWD_ENTRY;

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -r -s $src -d $dst -l $login -a $sd_ip_addr -p '$passwd' ", $output);

  $ret = terraform_generic_connect();
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


  if (!empty($dst))
  {
    $dst_path = dirname($dst);
    sms_log_error("  Scp_to_router dst=$dst, dst_path=$dst_path");

    if (($dst_path !== '.')&&($dst_path != '\/')&&($dst_path != '/'))
    {
      // Create the dest directory
      create_flash_dir($dst_path);
    }
  }

  terraform_generic_disconnect();

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;
  $sd_ip_addr = $sd->SD_IP_CONFIG;
  $login = $sd->SD_LOGIN_ENTRY;
  $passwd = $sd->SD_PASSWD_ENTRY;

  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst_disk/$dst -l $login -a $sd_ip_addr -p '$passwd' ", $output);

  $ret = terraform_generic_connect();

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
  check_file_size($src, $dst, false);

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

function check_file_size($local_file, $remote_file, $remove_remote_file = false)
{
  global $sms_sd_ctx;

  $filename = basename($local_file);
  $orig_size = filesize($local_file);
  $size = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "stat -c %s $remote_file");
  sms_log_error("Check_file_size remote_size=$size;");
  list($dummy,$size,$dummy) = preg_split("/\n/",$size,3);
  $size = trim($size);

  if (! empty($size) )
  {
    if ($size != $orig_size)
    {
      unlink($local_file);
      sms_log_error("transfering $remote_file failed: size $size found $orig_size awaited");
      throw new SmsException("transfering $remote_file failed: size $size found $orig_size awaited", ERR_SD_FILE_TRANSFER);
    }
  }
  else
  {
    sms_log_error("transfering $remote_file failed: file not found on device");
    throw new SmsException("transfering $remote_file failed: file not found on device", ERR_SD_FILE_TRANSFER);
  }
  sms_log_error("check_file_size Remote file size $size, new local file size=$orig_size");
  return SMS_OK;
}

?>