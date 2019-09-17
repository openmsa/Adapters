<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";
require_once load_once('nec_intersecvmlb', 'nec_intersecvmlb_common.php');

class NecIntersecvmlbsshConnection extends SshConnection
{
  public function do_start() {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
  
  public function do_connect() {
    nec_intersecvmlb_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmlb_connect:do_connect');
    global $sendexpect_result;
    $cnx_timeout = 10; // seconds

    try {
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i '/home/ncuser/.ssh/id_rsa' -o PreferredAuthentications=publickey -o ConnectTimeout={$cnx_timeout} '{$this->sd_login_entry}@{$this->sd_ip_config}'");
    }
    catch (SmsException $e) {
      $this->connect_alt_port();
    }

    // Manage password or auto connection (ssh keys)
    unset($tab);
    $tab[0] = 'assword';
    $tab[1] = 'PASSCODE';
    $tab[2] = '#';
    $tab[3] = '$';
    $tab[4] = 'Permission denied';

    $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    if ($index == 0 || $index == 1) {
      $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
    }
    if ($index == 2 || $index == 3) {
      $this->sendCmd(__FILE__.':'.__LINE__, "date");
    }
    if ($index == 4){
        throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    echo "SSH connection established to {$this->sd_ip_config}\n";
    $this->do_post_connect();
    $this->do_store_prompt();
    $this->do_start();

    nec_intersecvmlb_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmlb_connect:do_connect');

  }
  
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function nec_intersecvmlb_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
	global $sms_sd_ctx;

	$sms_sd_ctx = new NecIntersecvmlbsshConnection($sd_ip_addr, $login, $passwd, $port_to_use);

	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function nec_intersecvmlb_disconnect()
{
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}

?>
