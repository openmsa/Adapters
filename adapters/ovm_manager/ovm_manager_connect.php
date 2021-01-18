<?php
/*
 * 	Version: 0.1: ovm_manager_connect.php
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
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once load_once('ovm_manager', 'common.php');
require_once "$db_objects";

// return false if error, true if ok
function ovm_manager_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  try
  {
    $sms_sd_ctx = new OVMManagerSSHConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
    $sms_sd_ctx->setParam("PROTOCOL", "SSH");
  }
  catch (SmsException $e)
  {
    debug_dump($e);
    return $e->getCode();
  }
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function ovm_manager_disconnect()
{
  $sms_sd_ctx = null;
  return SMS_OK;
}

function ovm_manager_synchro_prompt()
{
  global $sms_sd_ctx;

  $msg = 'UBISyncro' . mt_rand(10000, 99999);
  $prompt = $sms_sd_ctx->getPrompt();
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "showversion");
}

class OVMManagerSSHConnection extends SshConnection
{
  public function do_store_prompt()
  {
    $this->setParam("newline_dos", true);

    global $sendexpect_result;
    echo "OVMManagerSSHConnection.do_store_prompt\n";
    $this->sendCmd(__FILE__ . ':' . __LINE__, "showversion");
    echo " 1 sendexpect\n";
 
    $tab[0] = '>';
    $index = sendexpect(__FILE__ . ':' . __LINE__, $this, 'showversion', $tab);
    echo " 2 sendexpect\n";

    $this->prompt = trim($sendexpect_result);
    if (strrchr($this->prompt, "\n") !== false)
    {
       $this->prompt = substr(strrchr($this->prompt, "\n"), 1);
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";

    // synchronize again
    $prompt = $this->prompt;
    sendexpectone(__FILE__ . ':' . __LINE__, $this, "showversion");

  }

  public function do_start() {
      $this->setParam('chars_to_remove', array("\033[00m", "\033[m"));
  }

}



?>
