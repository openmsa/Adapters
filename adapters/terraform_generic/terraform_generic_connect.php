<?php
/*
 * 	Version: 0.1: terraform_generic_connect.php
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
require_once load_once('terraform_generic', 'common.php');
require_once "$db_objects";

// return false if error, true if ok
function terraform_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  try
  {
    $sms_sd_ctx = new TerraformSSHConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
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
function terraform_generic_disconnect()
{
  $sms_sd_ctx = null;
  return SMS_OK;
}

function terraform_generic_synchro_prompt()
{
  global $sms_sd_ctx;

  $msg = 'UBISyncro' . mt_rand(10000, 99999);
  $prompt = $sms_sd_ctx->getPrompt();
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "terraform version");
}

class TerraformSSHConnection extends SshConnection
{

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

    echo "Prompt found: '{$this->prompt}' for {$this->sd_ip_config}\n";
  }

  public function do_start() {
      $this->setParam('chars_to_remove', array("\033[00m", "\033[m"));
      $this->do_store_prompt();
  }

}



?>
