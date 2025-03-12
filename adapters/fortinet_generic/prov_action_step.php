<?php
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'common.php');
require_once "$db_objects";

// -------------------------------------------------------------------------------------
// PROVISIONING
// -------------------------------------------------------------------------------------
function prov_action_step($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $provisioning_stage;
  global $SD;
  global $ipaddr;
  global $login;
  global $passwd;
  global $port;
  global $prov_result_message;

  $conf = new fortinet_generic_configuration($sdid, true);
  $action = $provisioning_stage['action'];

  $hostname = $SD->SD_HOSTNAME;
  $ping_var = $SD->SD_CONFIGVAR_list['PUBLIC_IP']->VAR_VALUE;
  $ping_arr = explode('/',$ping_var);
  $ping_ip = $ping_arr[0];
  $timezone = $SD->SD_CONFIGVAR_list['TIMEZONE']->VAR_VALUE;
  $rebooting = false;
  $disconnecting = false;

  switch ($action)
  {
    case 'FORMAT DISK':
      $rebooting = true;
      unset($commands);
      $commands[0] = "execute formatlogdisk";
      exec_cmd_expect($sms_csp, $commands, "Format Disk Failed");
      break;

      case 'REBOOT':
        $rebooting = true;
        unset($commands);
        $commands[0] = "execute reboot";
        exec_cmd_expect($sms_csp, $commands, "Reboot Failed");
        break;

    case 'TIMEZONE CHANGE':
      unset($commands);
      $commands[0] = "config system global";
      $commands[1] = "set timezone $timezone";
      $commands[2] = "end";
      exec_cmd_expect($sms_csp, $commands, "Timezone Change Failed");
      break;

    case 'TIMEZONE IMPORT':
      exec_timezone_import($sms_csp, $sdid);
      break;

    case 'PASSWORD CHANGE':
      $disconnecting = true;
      $password = $SD->SD_PASSWD_ENTRY;
      unset($commands);
      $commands[0] = "config system admin";
      $commands[1] = "edit admin";
      $commands[2] = "set password $password";
      $commands[3] = "end";
      exec_cmd_expect($sms_csp, $commands, "Password Change Failed");
      //$passwd is passed as parameter to JSAPROVISIONING, $password is password in DB
      $passwd = $password;
      break;

      case 'PASSWORD CHANGE FORTIWEB':
        $password = $SD->SD_PASSWD_ENTRY;
        unset($commands);
        $commands[0] = "config system admin";
        $commands[1] = "edit admin";
        $commands[2] = "set password $password";
        $commands[3] = "$password";
        $commands[4] = "end";
        $commands[5] = "end";
        exec_cmd_expect($sms_csp, $commands, "Password Change Failed");
        //$passwd is passed as parameter to JSAPROVISIONING, $password is password in DB
        $passwd = $password;
        break;

    case 'HOSTNAME CHANGE':
      unset($commands);
      $commands[0] = "config system global";
      $commands[1] = "set hostname $hostname";
      $commands[2] = "end";
      exec_cmd_expect($sms_csp, $commands, "Hostname Change Failed");
      break;

    case 'PING RESULT':
      $commands[0] = 'exec ping ' . $ping_ip;
      $result = exec_cmd_and_disconnect_when_failed($sms_csp, $commands, "error while pinging $ping_ip");
      if (preg_match("/(\d+)\spackets\stransmitted,\s(\d+)\spackets\sreceived,\s(\d+)%\spacket\sloss/", $result, $match))
      {
        $prov_result_message = $match[0];
      }
      save_result_file($result, "ping.result");
      break;
  }

  if ($rebooting || $disconnecting)
  {
    fortinet_generic_disconnect();
    if($disconnecting){
        $initial_wait_time = 60;
    } else {
        //$initial_wait_time = 300;
        $initial_wait_time = 120;
    }
    $ret = $conf->wait_until_device_is_up($initial_wait_time);
    if ($ret == SMS_OK)
    {
      fortinet_generic_connect($ipaddr, $login, $passwd, $port);
    }
    else
    {
      throw new SmsException("Connection Failed", $ret);
    }
  }

  return SMS_OK;
}

function exec_cmd_expect($sms_csp, $commands, $errormsg)
{
  global $sms_sd_ctx;

  $tab[0] = "#";
  $tab[1] = "y/n)";
  $tab[2] = 'lease enter new password agai';

  foreach ($commands as $command)
  {
    $index = 0;
    if($command == "end" && $errormsg == "Password Change Failed"){
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $command);
        continue;
    } else {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $command, $tab);
    }

    if ($index === 1)
    {
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "y");
    }
    else if ($index === 2)
    {
      continue;
    }
    else if ($index === -1)
    {
        throw new SmsException($errormsg, ERR_SD_CMDFAILED);
    }
  }
}

function exec_timezone_import($sms_csp, $sdid){

	$map_conf = array();
	$ret = get_repo_files_map($map_conf, $error, 'CommandDefinition');
	if ($ret !== SMS_OK)
	{
		throw new SmsException("Error when getting objects attached to this device", ERR_SD_CMDFAILED);
	}


	$found_timezone_obj = false;
	foreach ($map_conf as $mkey => $mvalue)
	{
		if (strpos($mvalue, 'system') !== false)
		{
			$found_timezone_obj = true;
			break;
		}
	}

	if (!$found_timezone_obj){
		throw new SmsException("No system object attached to this device - Timezone import failed", ERR_SD_CMDFAILED);
	}

	$cmd = "/opt/sms/bin/sms -e JSCALLCOMMAND -i \"$sdid IMPORT 2\" -c '{\"system\":0}'";
	$ret = exec_local(__FILE__.':'.__LINE__, $cmd, $output);
}

function exec_cmd_and_disconnect_when_failed($sms_csp, $commands, $errormsg)
{
  global $sms_sd_ctx;

  $result = "";

  foreach ($commands as $command)
  {
    $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $command, "#");
    if (empty($result))
    {
      throw new SmsException($errormsg, ERR_SD_CMDFAILED);
    }
  }
  return $result;
}

?>