<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";

function init_connection($conn)
{
    $IS_VDOM_ENABLED=false;

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'get system status', '#');
    if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
                            //If VDOM is enabled for generic commands do config global
                    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config global', '(global) #', 40000);
                    $IS_VDOM_ENABLED=true;
    }

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', ' #', 2000);
    if (strpos($buffer, "parse error") !== false) {
        return;
    }

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'config system console', '(console) #', 40000);
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'set output standard', '(console) #', 40000);
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'end', '#');

    if ($IS_VDOM_ENABLED){
            $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $conn, 'end', '#');
    }

}


class FortinetGenericsshConnection extends SshConnection
{
  public function do_connect() {
    global $sendexpect_result;
    $cnx_timeout = 10; // seconds

    try {
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout={$cnx_timeout} '{$this->sd_login_entry}@{$this->sd_ip_config}'");

      unset($tab);
      $tab[0] = 'added';

      try {
        $this->expect(__FILE__.':'.__LINE__, $tab, $cnx_timeout * 1000);
      } catch (SmsException $e) {
        throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
      }

      if(!preg_match('/Permanently\sadded/', $sendexpect_result, $match)) {
        $this->connect_alt_port();
      }
    }
    catch (SmsException $e) {
      $this->connect_alt_port();
    }

    // Manage password or auto connection (ssh keys)
    unset($tab);
    $tab[0] = 's password:'; //adding the ":" to avoid confusion about the warning that we receive for newer IOS devices
    $tab[1] = 'ld password:';
    $tab[2] = 'ew password:';
    $tab[3] = 'ew Password:';
    $tab[4] = 'PASSCODE';
    $tab[5] = '#';
    $tab[6] = '$';
    $tab[7] = 'Permission denied';

    $loop_count =0;
    $index = 0;
    foreach ($tab as $t)
    {
      if (strpos($sendexpect_result, $t) !== false){
        break;
      }
      $index++;
    }
    if ($index > 7)
    {
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }
    while (($index == 0 || $index == 1 || $index == 2 || $index == 3) && $loop_count < 6) {
      if ($index == 0 || $index == 1) {
        $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
      }
      if ($index == 2 || $index == 3) {
        // sd_admin_passwd_entry is used as the new password for fortinet re-newal password requirement.
        $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
      }
      $loop_count ++;
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    if ($index == 7){
      throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    echo "SSH connection established to {$this->sd_ip_config}\n";
    $this->do_store_prompt();
    $this->do_start();
  }

  public function do_store_prompt()
  {
  	$network = get_network_profile();

  	$IS_VDOM_ENABLED=false;

    init_connection($this);

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if ((strpos($buffer, 'License Status') === false) || (strpos($buffer, 'License Status: Valid') !== false) || (strpos($buffer, 'License Status: Warning') !== false))
    {
    	if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
			//If VDOM is enabled for generic commands do config global
	    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
	    	$IS_VDOM_ENABLED=true;
    	}
      if ($IS_VDOM_ENABLED){
      	//If the device is a VDOM come out of global mode and enter vdom mode
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
		    $cmd = "edit root";
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, '', '#',40000);
    	$this->prompt = trim($buffer);
    	$this->prompt = substr(strrchr($buffer, "\n"), 1);
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
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
    $network = get_network_profile();
    $SD = &$network->SD;
    $dev_name=$SD->SD_HOSTNAME;

    $IS_VDOM_ENABLED=false;
    init_connection($this);

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if ((strpos($buffer, 'License Status') === false) || (strpos($buffer, 'License Status: Valid') !== false) || (strpos($buffer, 'License Status: Warning') !== false))
    {
      if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
        //If VDOM is enabled for generic commands do config global
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
        $IS_VDOM_ENABLED=true;
      }

      if ($IS_VDOM_ENABLED){
        //If the device is a VDOM come out of global mode and enter vdom mode
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
        $cmd = "edit $dev_name";
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
      $this->prompt = " # ";
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }
}

class FortinetsshKeyConnection extends SshKeyConnection
{

  public function do_store_prompt()
  {
    $network = get_network_profile();

    $IS_VDOM_ENABLED=false;
    init_connection($this);

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if (strpos($buffer, 'License Status') == false || strpos($buffer, 'License Status: Valid') !== false || strpos($buffer, 'License Status: Warning') !== false)
    {
      if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
        //If VDOM is enabled for generic commands do config global
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
        $IS_VDOM_ENABLED=true;
      }

      if ($IS_VDOM_ENABLED){
        //If the device is a VDOM come out of global mode and enter vdom mode
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
        $cmd = "edit root";
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
      $this->prompt = " # ";
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
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

  $data = json_decode($model_data, true);
  $class = $data['class'];
  if (isset($data['priv_key']))
  {
    $priv_key = $data['priv_key'];
  }

  $sms_sd_ctx = new $class($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);

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
