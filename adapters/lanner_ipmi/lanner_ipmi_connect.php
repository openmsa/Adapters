<?php
/*
 * 	Version: 0.1: linux_generic_connect.php
 *  	Created: Jun 7, 2012
 *  	Available global variables
 *  	$sms_sd_ctx        	pointer to sd_ctx context to retreive usefull field(s)
 *  	$sms_sd_info        	sd_info structure
 *  	$sms_csp            	pointer to csp context to send response to user
 *  	$sdid
 *  	$sms_module         	module name (for patterns)
 *  	$SMS_RETURN_BUF    	string buffer containing the result
 */

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_connection.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/expect.php';
require_once "$db_objects";

// return false if error, true if ok
function lanner_ipmi_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;
  try
  {
    $sms_sd_ctx = new IpmiBashConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
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
function lanner_ipmi_disconnect()
{
  $sms_sd_ctx = null;
  return SMS_OK;
}

function bash_generic_synchro_prompt()
{
  global $sms_sd_ctx;

  $msg = 'UBISyncro' . mt_rand(10000, 99999);
  $prompt = $sms_sd_ctx->getPrompt();
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "echo -n {$msg}", "{$msg}{$prompt}");
}

class BashConnection extends GenericConnection {

  public function do_connect() {
    global $sendexpect_result;
    $cnx_timeout = 10; // seconds
    parent::connect('/bin/bash');
    // Manage password or auto connection (ssh keys)
    unset($tab);
    $tab[0] = 'assword:'; //adding the ":" to avoid confusion about the warning that we recieve for newer IOS devices
    $tab[1] = 'PASSCODE';
    $tab[2] = '#';
    $tab[3] = '$';
    $tab[4] = 'Permission denied';

    $index = 0;
    foreach ($tab as $t)
    {
      if (strpos($sendexpect_result, $t) !== false){
        break;
      }
      $index++;
    }
    if ($index > 4)
    {
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }
    if ($index == 0 || $index == 1) {
      $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
    }
    if ($index == 4){
    	throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    echo "Bash shell has been opened\n";
    $this->do_post_connect();
    $this->do_store_prompt();
    $this->do_start();
  }

  public function do_post_connect() {
  }

  public function do_store_prompt() {
    echo 'DEBUG: DO_STORE_PROMPT';
    $this->sendCmd(__FILE__.':'.__LINE__, '');
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $this->expect(__FILE__.':'.__LINE__, $tab);
    $this->prompt = trim($this->last_result);

    // Remove Escape terminal sequence if any
    $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '',$this->prompt);
    $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '',$this->prompt);
    $this->prompt = preg_replace('/[\x03|\x1a]/', '', $this->prompt);

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }

  public function do_start() {

  }

  public function do_pre_disconnect() {
  }


  public function do_disconnect() {
    $this->do_pre_disconnect();
    parent::disconnect();
  }

}

class IpmiBashConnection extends BashConnection
{
  public function do_store_prompt()
  {
    global $sendexpect_result;

    $this->sendCmd(__FILE__ . ':' . __LINE__, "stty -echo");
    $this->sendCmd(__FILE__ . ':' . __LINE__, "stty -onlcr ocrnl -echoctl -echoe -opost rows 0 columns 0 line 0");
    $tab[0] = '#';
    $tab[1] = '$';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, '', $tab);
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, '', $tab);

    sendexpectone(__FILE__ . ':' . __LINE__, $this, 'echo -n UBISynchroForPrompt', 'UBISynchroForPrompt');

    $tab[0] = '#';
    $tab[1] = '$';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, 'echo', $tab);

    $this->prompt = trim($sendexpect_result);
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

  public function do_start() {
      $this->setParam('chars_to_remove', array("\033[00m", "\033[m"));
  }

}

?>
