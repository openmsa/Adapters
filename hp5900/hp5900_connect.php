<?php
/*
*  	Available global variables
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

class deviceConnection extends SshConnection
{
  protected $default_protocol = "ssh"; //[SWF#130] NCOS Bugfix 2017.10.25 Set the default protocol

  public function do_connect()
  {
    try {
      //[SWF#130] NCOS Bugfix 2017.10.25 START Defined rules when to connect by SSH or TELNET
      $do_ssh = TRUE;
      if ($this->sd_management_port === 22) {
        $do_ssh = TRUE;
      } elseif ($this->sd_management_port === 23) {
        $do_ssh = FALSE;
      } else {
        if ($this->default_protocol === "ssh") {
          $do_ssh = TRUE;
        } else {
          $do_ssh = FALSE;
        }
      }

      if ($do_ssh) {
        parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
      } else {
        parent::connect("telnet '{$this->sd_ip_config}' {$this->sd_management_port}");
      }
      //[SWF#130] NCOS Bugfix 2017.10.25 END
    }
    catch (SmsException $e) {
    // try the alternate port
      if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback)) {
        try {
          parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        } catch (SmsException $e) {
          parent::connect("telnet '{$this->sd_ip_config}'");
        }
      } else {
        parent::connect("telnet '{$this->sd_ip_config}'");
      }
    }
  }

  public function sendCmd($origin, $cmd)
  {
    return $this->send($origin, "$cmd\n");
  }

  // extract the prompt
  function extract_prompt()
  {
    /* for synchronization */
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'system-view', ']');
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'quit', '>');
    $buffer = trim($buffer);
    $buffer = substr(strrchr($buffer, "\n"), 1);  // get the last line
    $this->setPrompt($buffer);
  }

}

// return false if error, true if ok
function device_connect($sd_ip_addr = null,$login = null, $passwd = null, $adminpasswd = null,$port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new deviceConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;

  if (empty($login))
  {
    $login = $sd->SD_LOGIN_ENTRY;
    $passwd = $sd->SD_PASSWD_ENTRY;
    $adminpasswd = $sd->SD_PASSWD_ADM;
  }

  //[SWF#130] NCOS Bugfix 2017.10.25 START Modified to support telnet's login prompt
  unset($tab);
  $tab[0] = ">";
  $tab[1] = "#";
  $tab[2] = "ogin:";
  $tab[3] = "assword:";
  //[SWF#130] NCOS Bugfix 2017.10.25 END

  $index = 99;
  $login_state = 0;

  for ($i = 1; ($i <= 20) && ($login_state < 4); $i++)
  {
    switch ($index)
    {
      case -1:
        device_disconnect();
        throw new SmsException("$origin: connection error for {$sd_ip_addr}", ERR_SD_TIMEOUTCONNECT);

      case 99: // wait for router
        $index = $sms_sd_ctx->expect(__FILE__.':'.__LINE__, $tab);
        switch ($login_state)
        {
          case 1:
            if ($index === 2)
            {
              device_disconnect();
              throw new SmsException("$origin: connection error for {$sd_ip_addr}",  ERR_SD_AUTH);
            }
            break;

          case 2:
            if ($index > 1)
            {
              device_disconnect();
              throw new SmsException("$origin: connection error for {$sd_ip_addr}",  ERR_SD_AUTH);
            }
            break;

          case 3:
            if ($index !== 1)
            {
              device_disconnect();
              throw new SmsException("$origin: connection error for {$sd_ip_addr}",  ERR_SD_ADM_AUTH);
            }
            break;
        }
        break;

      case 0: // ">"
	// sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "system-view", "]");
        //$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, $adminpasswd);
        $index = 1;
        $login_state = 2;
        break;

      case 1: // "#"
        $login_state = 4;
        //sms_log_info("HERE - 1");
	 break;

      //[SWF#130] NCOS Bugfix 2017.10.25 START Modified to support telnet's login prompt
      case 2: // "login" 
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  $login);
        $index = 99;
        $login_state = 1;
        break;

      case 3: // "password"
        //echo "Sending Password $passwd\n";
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  $passwd);
        $index = 99;
        $login_state = 1;
        break;
      //[SWF#130] NCOS Bugfix 2017.10.25 END
    }
  }
  $sms_sd_ctx->extract_prompt();
  sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "screen-length disable");
  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function device_disconnect($clean_exit = false)
{
  global $sms_sd_ctx;
  global $sdid;

  if (!isset($sms_sd_ctx)) {
    echo "device_disconnect => already disconnected\n";
    return;
  }

  if ($clean_exit)
  {
    // Exit from config mode
    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = ")#";
    $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, '');
    $index = $sms_sd_ctx->expect(__FILE__.':'.__LINE__, $tab);
    for ($i = 1; ($i <= 10) && ($index === 1); $i++)
    {
      $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'quit');
      $index = $sms_sd_ctx->expect(__FILE__.':'.__LINE__, $tab);
    }
  }

  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

