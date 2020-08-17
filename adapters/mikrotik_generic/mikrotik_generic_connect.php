<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";


class MikrotikGenericsshConnection extends SshConnection
{

  public function do_connect() {
    global $sendexpect_result;
    $cnx_timeout = 10; // seconds

    $this->do_start();

    try {
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout={$cnx_timeout} '{$this->sd_login_entry}+ct@{$this->sd_ip_config}'");

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
    $tab[0] = 'assword:'; //adding the ":" to avoid confusion about the warning that we recieve for newer IOS devices
    $tab[1] = 'PASSCODE';
    $tab[2] = '#';
    $tab[3] = '$';
    $tab[4] = '] > ';
    $tab[5] = 'Permission denied';

    $index = 0;
    foreach ($tab as $t)
    {
      if (strpos($sendexpect_result, $t) !== false){
        break;
      }
      $index++;
    }
    if ($index > 5)
    {
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }
    if ($index == 0 || $index == 1)
    {
      $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
    }
    if ($index == 5)
    {
    	throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    echo "SSH connection established to {$this->sd_ip_config}\n";

    $this->do_store_prompt();
  }


  public function do_store_prompt()
  {
        echo "******************Storing the PROMPT***************************\n";
        $nop = 'nothing';
        $prompt = '] >';
        $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, $nop, $prompt);
        $start = strpos($buffer, $nop);
        $end = strrpos($buffer, $prompt);
        if ($start === false || $end === false)
        {
          $this->prompt = $prompt;
        }
        else
        {
          $start += strlen($nop);
          $end += strlen($prompt);
          $buffer = substr($buffer, $start, $end - $start);
          $this->prompt = trim($buffer);
        }
        echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }

  public function do_start()
  {
    //$this->setParam('suppress_echo', true);
    //$this->setParam('suppress_prompt', true);
    $this->setParam('chars_to_remove', array("\033[9999B", "\033[6n"), "\033[Z");
    $this->setParam("newline_dos", true);
  }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function mikrotik_generic_connect($sd_ip_addr = null, $login = null, $passwd = null,  $adminpasswd = null, $port_to_use = null)
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

  $sms_sd_ctx = new MikrotikGenericsshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function mikrotik_generic_disconnect()
{
  global $sms_sd_ctx;
  if (!is_null($sms_sd_ctx) && method_exists($sms_sd_ctx, 'sendCmd'))
  {
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'quit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
