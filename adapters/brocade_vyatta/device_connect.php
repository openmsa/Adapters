<?php
/*
 *  	Available global variables
 *  	$sdid
 *  	$sms_module         	module name (for patterns)
 *  	$SMS_RETURN_BUF    	string buffer containing the result
 */

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';

require_once "$db_objects";
class deviceConnection extends SshConnection
{
  // Send command
  public function sendCmd($origin, $cmd)
  {
    return $this->send($origin, "$cmd\n");
  }

  public function do_store_prompt()
  {
    global $sendexpect_result;

    $this->sendCmd(__FILE__ . ':' . __LINE__, "stty -echo");
    $this->sendCmd(__FILE__ . ':' . __LINE__, "stty -onlcr ocrnl -echoctl -echoe -opost rows 0 columns 0 line 0");
    $this->sendCmd(__FILE__ . ':' . __LINE__, "set terminal width 1024");
    $this->sendCmd(__FILE__ . ':' . __LINE__, "set terminal length 8192");
    $tab[0] = '#';
    $tab[1] = '$';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, '', $tab);
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, '', $tab);

    sendexpectone(__FILE__ . ':' . __LINE__, $this, 'echo -n UBISynchroForPrompt', 'UBISynchroForPrompt');

    $tab[0] = '#';
    $tab[1] = '$';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, 'echo', $tab);

    $this->prompt = trim($sendexpect_result);
    $this->prompt = preg_replace("@UBISynchroForPrompt@", "", $this->prompt);
    if (strrchr($this->prompt, "\n") !== false)
    {
      $this->prompt = substr(strrchr($this->prompt, "\n"), 1);
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";

    // synchronize again


    $msg = 'UBISyncro' . mt_rand(10000, 99999);
    $prompt = $this->prompt;
    sendexpectone(__FILE__ . ':' . __LINE__, $this, "echo -n {$msg}", "{$msg}{$prompt}");
  }

  public function do_start()
  {
    $this->setParam('chars_to_remove', array("\033[00m", "\033[m", "[?1h=", "[K[?1l>"));
    $this->setParam('suppress_prompt', true);
    $this->setParam('suppress_echo', true);
  }
}


function device_synchro_prompt()
{
  global $sms_sd_ctx;

  $msg = 'UBISyncro' . mt_rand(10000, 99999);
  $prompt = $sms_sd_ctx->getPrompt();
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "echo -n {$msg}", "{$msg}{$prompt}");
}

// return false if error, true if ok
function device_connect($login = null, $passwd = null, $adminpasswd = null, $sd_ip_addr = null, $port_to_use = null)
{  global $sms_sd_ctx;

  try
  {
    $sms_sd_ctx = new deviceConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
    $sms_sd_ctx->setParam("PROTOCOL", "SSH");
  }
  catch (SmsException $e)
  {
    debug_dump($e);
    return ERR_SD_CONNREFUSED;
  }
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function device_disconnect($clean_exit = false)
{
  global $sms_sd_ctx;
  global $sdid;

  if (!isset($sms_sd_ctx))
  {
    echo "device_disconnect => already disconnected\n";
    return;
  }

  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

