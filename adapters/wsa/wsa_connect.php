<?php
/*
* 	Version: 0.1: wsa_connect.php
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
require_once "$db_objects";

class WSAConnection extends SshConnection
{

  public function do_store_prompt() {
    parent::do_store_prompt();
    $this->prompt = preg_replace('/.*\n/', '', $this->prompt);
  }

  public function reboot()
  {
    unset($tab);
    $tab[0] = 'try later';
    $tab[1] = '(Y/N)';
    $tab[2] = 'Shutting down';
    $this->sendCmd(__FILE__.':'.__LINE__, "reload");
    $index = $this->expect(__FILE__.':'.__LINE__, $tab);

    while ($index < 2)
    {
      switch ($index)
      {
        case 0:
          sleep(5);
          $this->sendCmd(__FILE__.':'.__LINE__, "reload");
          $index = $this->expect(__FILE__.':'.__LINE__, $tab);
          break;
        case 1:
          $this->send(__FILE__.':'.__LINE__, "Y");
          $index = $this->expect(__FILE__.':'.__LINE__, $tab);
          break;
      }
    }
  }
}


// return false if error, true if ok
function wsa_connect($login = null, $passwd = null, $sd_ip_addr = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new WSAConnection($sd_ip_addr, $login, $passwd, null, $port_to_use);

  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function wsa_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>