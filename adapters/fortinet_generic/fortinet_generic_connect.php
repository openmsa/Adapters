<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";

function global_do_store_prompt($conn){

  $tab[0] = "$";
  $tab[1] = "#";
  global $sendexpect_result;

   //1) Check if it is a VDOM and get the system status
   $IS_VDOM_ENABLED = false;
   //$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'execute update-now', '',10000); //no output
   $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config global', '(global)');
   $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', '(console)');
   $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'set output standard', '(console)');
   $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);
   $get_system_status = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'get system status', $tab);
   $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);

   if (strpos($get_system_status, 'Virtual domain configuration: enable') !== false) {
     //IT IS A VDOM, we should run at first 'config global'
     $IS_VDOM_ENABLED = true;
     try {  // VDOM is enabled for generic commands do config global
       $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config global', '(global)', 10000);
       $config_console = 'OK';
     } catch (SmsException $e) {
       $config_console = 'NOK';
     }
   } elseif (strpos($get_system_status, 'License Status') === false) {
     // It is a WAF (we don't have the line 'License Status'
     // We can get license status with "diagnose debug vm license | grep 'License info' "  cf License info : Valid license.
     $config_console = 'UNKNOWN';
   } elseif ((strpos($get_system_status, 'License Status: Valid') !== false) || (strpos($get_system_status, 'License Status: Warning') !== false)) {
     // It is a UTM with Valid or Warning license, so you can run 'config system console'
     $config_console = 'OK';
   } elseif (strpos($get_system_status, 'License Status: Pending') !== false) {
     // It is a UTM with Pending License (after one reboot), we have to wait
     $config_console = 'WAIT';
   } else {
     //  UTM with no license or invalid license, so you can run not 'config system console'
     $config_console = 'NOK';
   }
   sms_log_info(__FILE__." config_console=$config_console");

   if ($config_console == 'UNKNOWN') {
     // On waf, run  'config system console' with a very short timeout 10 secondes
     try {
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config global', '(global)',10000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', '(console)',10000);
      $config_console2 = 'OK';
     } catch (SmsException $e) {
       $config_console2 = 'NOK';
     }
     if ($config_console2 == 'OK') {
       $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'set output standard', '(console)');
       $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);
     } else {
       //NO OK, run only blank command to get the prompt
       $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, '', $tab,40000);
    }
  } elseif ($config_console == 'OK') {
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config global', '(global)',10000);
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', '(console)');
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'set output standard', '(console)');
    $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);
    if ($IS_VDOM_ENABLED) {
      //If the device is a VDOM come out of global mode and enter vdom mode
      $network  = get_network_profile();
      $SD       = &$network->SD;
      $dev_name = $SD->SD_HOSTNAME;
      $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config vdom', '(vdom)', 40000);
      $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, "edit $dev_name", $tab, 40000);
      $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, $cmd, $tab, 40000);

    }
  } elseif ($config_console == 'WAIT') {

    //After reboot, the UTM or WAF device check the license, and during this time we can not run the 'config system console', we have to wait a bit
    $bad_console = true;
    $loop_count = 0;
    while ($bad_console && $loop_count++ < 10 ) {
      try {
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', '(console)',60000);
        $bad_console = false;
      } catch (SmsException $e) {
        $err      = $e->getMessage();
        $err_code = $e->getCode();
        sms_log_error(__FILE__." wait valid license loop $loop_count, error=$err, (err_code=$err_code)");
        if ($err_code != ERR_SD_CMDTMOUT || $loop_count == 10) {
          $status_message = "Connection : $err";
          //return $err_code;
          throw new SmsException($status_message, $err_code);
        }
      }
    }
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'set output standard', '(console)');
    $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, 'end', $tab);
  } else {
    //NO OK, run only blanc command to get the prompt
    $buffer = sendexpect(__FILE__ . ':' . __LINE__, $conn, '', $tab, 40000);
  }
  if (empty($buffer)) {
    $buffer = " # ";
  }

  $prompt = trim($sendexpect_result);
  $prompt = substr(strrchr($prompt, "\n"), 1);
  sms_log_info(__FILE__." get prompt =$prompt");
  return $prompt;
}


class FortinetGenericsshConnection extends SshConnection
{
  public function do_connect() {
    global $sendexpect_result;
    $network = get_network_profile();
    $SD = &$network->SD;
    $cnx_timeout = 10; // seconds

    try {
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout={$cnx_timeout} '{$this->sd_login_entry}@{$this->sd_ip_config}'");

      unset($tab);
      $tab[0] = 'added';

      try {
        $this->expect(__FILE__.':'.__LINE__, $tab, $cnx_timeout * 1000);
      } catch (SmsException $e) {
        throw new SmsException($e->getMessage(), $e->getCode());
      }

      if (!preg_match('/Permanently\sadded/', $sendexpect_result, $match)) {
        $this->connect_alt_port();
      }
    }
    catch (SmsException $e) {
      $this->connect_alt_port($e);
    }

    // Manage password or auto connection (ssh keys)
    unset($tab);
    $tab[0] = 's password:'; //adding the ":" to avoid confusion about the warning that we receive for newer IOS devices
    $tab[1] = 'ld password:';
    $tab[2] = 'ew password:';
    $tab[3] = 'ew Password:';
    $tab[4] = 'irm Password:';
    $tab[5] = 'PASSCODE';
    $tab[6] = '#';
    $tab[7] = '$';
    $tab[8] = 'Permission denied';

    $loop_count =0;
    $index = 0;

    foreach ($tab as $t)
    {
      if (strpos($sendexpect_result, $t) !== false){
        break;
      }
      $index++;
    }
    if ($index > 8)
    {
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }
    while (($index == 0 || $index == 1 || $index == 2 || $index == 3 || $index == 4) && $loop_count < 6) {
      // case for regular prompt for password or prompt for old password:
      if (($index == 0 || $index == 1)) {
      	$this->sendCmd(__FILE__.':'.__LINE__, "$this->sd_passwd_entry");
      }
      if ($index == 2 || $index == 3 || $index == 4) {
      // case for prompt for new password.
      // this is used when automating FGT onboarding on AWS for instance
      // sd_admin_passwd_entry is used to store the new password for fortinet re-newal password requirement.
        $this->sendCmd(__FILE__.':'.__LINE__, "{$SD->SD_PASSWD_ADM}");
      }
      $loop_count ++;
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    if ($index == 8) {
      throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    echo "SSH connection established to {$this->sd_ip_config}\n";
    $this->do_store_prompt();
    $this->do_start();
  }

  public function do_store_prompt()
  {
    $this->prompt = global_do_store_prompt($this);
    echo "Prompt found FortinetGenericsshConnection: {$this->prompt} for {$this->sd_ip_config}\n";
  }

  public function do_start()
  {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
}

class FortinetVDOMsshConnection extends FortinetGenericsshConnection
{
  public function do_store_prompt()
  {
    $this->prompt = global_do_store_prompt($this);
    echo "Prompt found FortinetVDOMsshConnection: {$this->prompt} for {$this->sd_ip_config}\n";
  }
}

class FortinetsshKeyConnection extends SshKeyConnection
{

  public function do_store_prompt()
  {
    $this->prompt = global_do_store_prompt($this);
    echo "Prompt found FortinetsshKeyConnection: {$this->prompt} for {$this->sd_ip_config}\n";
  }
  public function do_start()
  {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function fortinet_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;
  global $model_data;
  global $priv_key;
  global $SMS_OUTPUT_BUF;

  $data = json_decode($model_data, true);
  $class = $data['class'];
  if (isset($data['priv_key']))
  {
    $priv_key = $data['priv_key'];
  }

  try {
    $sms_sd_ctx = new $class($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
  } catch (SmsException $e) {
    $SMS_OUTPUT_BUF = $e->getMessage();
    return $e->getCode();
  }

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function fortinet_generic_disconnect()
{
  global $sms_sd_ctx;
  if (!is_null($sms_sd_ctx) && method_exists($sms_sd_ctx, 'sendCmd'))
  {
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

