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
  protected $default_protocol = "ssh"; //[SWF#129] NCOS Bugfix 2017.10.25 Set the default protocol

  public function do_connect()
  {
    //[SWF#98] NSP Bugfix 2017.07.18 ADD START
    try {
      //[SWF#129] NCOS Bugfix 2017.10.25 START Defined rules when to connect by SSH or TELNET
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
        parent::connect("ssh -p {$this->sd_management_port} -o stricthostkeychecking=no -o userknownhostsfile=/dev/null -o numberofpasswordprompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
      } else {
        parent::connect("telnet '{$this->sd_ip_config}' '{$this->sd_management_port}'");
      }
      //[SWF#129] NCOS Bugfix 2017.10.25 END
    }
    catch (SmsException $e) {
      //try the alternate port
      if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback)) {
        try {
          parent::connect("ssh -p {$this->sd_management_port_fallback} -o stricthostkeychecking=no -o userknownhostsfile=/dev/null -o numberofpasswordprompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        } catch (SmsException $e) {
          parent::connect("telnet '{$this->sd_ip_config}'");
        }
      } else {
        parent::connect("telnet '{$this->sd_ip_config}'");
      }
    }
    //[SWF#98] NSP Bugfix 2017.07.18 ADD END
  }

  public function sendCmd($origin, $cmd)
  {
    return $this->send($origin, "$cmd\n");
  }

  // extract the prompt
  function extract_prompt()
  {
    /* for synchronization */
    //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
    $buffer = trim($buffer);
    $buffer = substr(strrchr($buffer, "\n"), 1);  // get the last line
    //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
    $this->setPrompt($buffer); // original was $sms_sd_ctx
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

  unset($tab);
  $tab[0] = ">";
  $tab[1] = "#";
  $tab[2] = "sername:";
  $tab[3] = "assword:";
  $tab[4] = "ress any key to continue";    /*[SWF#98] NSP Bugfix 2017.07.18 ADD */

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
        sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "enable", "assword:");
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, $adminpasswd);
        $index = 99;
        $login_state = 3;
        break;

      case 1: // "#"
        $login_state = 4;
        break;

      case 2: // "Username"
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  $login);
        $index = 99;
        $login_state = 1;
        break;

      case 3: // "password"
        echo "Sending Password $passwd\n";
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__,  $passwd);
        $index = 99;
		   // $login_state = 2;
			$login_state = 0;
		break;
        
      //[SWF#98] NSP Bugfix 2017.07.20 ADD START
      case 4:
        $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, " ");//send a blank command   
        $index = 99;
        $login_state = 0;
        break;
      //[SWF#98] NSP Bugfix 2017.07.20 ADD END

    }
  }
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf t", '(config)#');
  //[SWF#101] NSP Bugfix 2017.07.21 ADD
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "console local-terminal none", '(config)#');
  //[SWF#99] NSP Bugfix 2017.07.17 Modified
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "no page", '(config)#');
  //[SWF#110] NSP Bugfix 2017.09.04 MODIFIED START 
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "terminal width 1920", '(config)#');
  //[SWF#110] NSP Bugfix 2017.09.04 MODIFIED END
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit",'#');
  $sms_sd_ctx->extract_prompt();
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
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
    $tab[1] = "#";
    $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  }

  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

