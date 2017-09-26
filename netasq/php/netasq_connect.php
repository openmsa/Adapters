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

require_once load_once('netasq', 'common.php');

require_once "$db_objects";

class netasqConnection extends GenericConnection {

  public function do_connect() {

    parent::connect("cd /opt/sms/bin/netasq && ./nsrpc '{$this->sd_login_entry}:{$this->sd_passwd_entry}@{$this->sd_ip_config}'");

    unset($tab);
    $tab[0] = 'SRPClient>';

    $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    if ($index === 0) {
      echo "Secure connection established to {$this->sd_ip_config}\n";
    }

    $this->prompt = 'SRPClient>';

    $this->sendexpectone(__FILE__.':'.__LINE__, 'modify off', 'SRPClient>');
  }

  public function do_disconnect() {
    parent::disconnect();
  }
}

// Connect
// return false if error, true if ok
function netasq_connect($sd_ip_addr = '', $login = '', $passwd = '')
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new netasqConnection($sd_ip_addr, $login, $passwd);

  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function netasq_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
