<?php
/*
 * 	Version: 0.1: nec_ipasolink_connect.php
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
require_once load_once('nec_ipasolink', 'common.php');
require_once "$db_objects";

// return false if error, true if ok
function nec_ipasolink_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  try
  {
    $sms_sd_ctx = new NeciPasolinkSshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
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
function nec_ipasolink_disconnect()
{
  $sms_sd_ctx = null;
  return SMS_OK;
}

function nec_ipasolink_synchro_prompt()
{
  global $sms_sd_ctx;

  $msg = 'UBISyncro' . mt_rand(10000, 99999);
  $prompt = $sms_sd_ctx->getPrompt();
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "echo -n {$msg}", "{$msg}{$prompt}");
}

class NeciPasolinkSshConnection extends SshConnection
{

  public function do_connect() {
    global $sendexpect_result;
    $cnx_timeout = 10; // seconds

    try {
      parent::connect("stty cols 1024; stty lines 1024;ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout={$
cnx_timeout} '{$this->sd_login_entry}@{$this->sd_ip_config}'");

      unset($tab);
      $tab[0] = 'added';

      try {
        $this->expect(__FILE__.':'.__LINE__, $tab, $cnx_timeout * 1000);
      } catch (SmsException $e) {
        throw new SmsException($e->getMessage(), $e->getCode());
      }

      if(!preg_match('/Permanently\sadded/', $sendexpect_result, $match)) {
        $this->connect_alt_port();
      }
    }
    catch (SmsException $e) {
      $this->connect_alt_port($e);
    }

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

    echo "SSH connection established to {$this->sd_ip_config}\n";
    $this->do_post_connect();
    $this->do_store_prompt();
    $this->do_start();
  }


  public function do_post_connect()
  {
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

    sendexpectone(__FILE__.':'.__LINE__, $this, "terminal scroll local off",'#');
  }

  public function do_store_prompt()
  {
    /* for synchronization */
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf', '(config)#');
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
    $buffer = trim($buffer);
    $buffer = substr(strrchr($buffer, "\n"), 1);  // get the last line
    $this->setPrompt($buffer);
  }

  public function do_start()
  {
  }

}



?>
