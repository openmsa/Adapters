<?php
/*
 * Version: $Id: netasq_connect.php 24050 2009-11-23 11:26:14Z tmt $
 * Created: Jun 17, 2008
 * Available global variables
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 */

// Open/close the session dialog with the router

// For communication with the router
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_connection.php';

require_once "$db_objects";

class connect_cli extends GenericConnection {

  public function do_connect() {

    try {
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
    } catch (SmsException $e) {
      throw new SmsException($e->getMessage(), $e->getCode(), __FILE__ . ':' . __LINE__ );
    }

    unset($tab);
    $tab[0] = 'assword:';

    $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    $this->sendexpectone(__FILE__.':'.__LINE__, $this->sd_passwd_entry, '>');
    $this->prompt = 'SRPClient>';
    $this->sendexpectone(__FILE__.':'.__LINE__, 'cli', 'assword');
    $this->sendexpectone(__FILE__.':'.__LINE__, $this->sd_passwd_entry);

    echo "Secure connection established to {$this->sd_ip_config}\n";

    $this->sendexpectone(__FILE__.':'.__LINE__, 'modify off');
  }

  public function do_disconnect() {
    $this->sendexpectone(__FILE__.':'.__LINE__, "quit", '>');
    $this->sendCmd(__FILE__.':'.__LINE__, 'exit');
    parent::disconnect();
  }

}


function connect($sd_ip_addr = '', $login = '', $passwd = '')
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new connect_cli($sd_ip_addr, $login, $passwd);

  return SMS_OK;
}

// Disconnect
function disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}
