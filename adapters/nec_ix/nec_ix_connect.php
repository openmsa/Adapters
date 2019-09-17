<?php
/*
*       Created: Dec 06, 2018
*       Available global variables
*       $sdid
*       $sms_module             module name (for patterns)
*       $SMS_RETURN_BUF         string buffer containing the result
*/

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';

require_once "$db_objects";

// return false if error, true if ok
function device_connect($sd_ip_addr = null,$login = null, $passwd = null, $adminpasswd = null,$port_to_use = null)
{
  $default_protocol = "ssh";

  global $sms_sd_ctx;

  $net_profile = get_network_profile();
  $sd = &$net_profile->SD;

  $do_ssh = TRUE;
  if ($sd->SD_MANAGEMENT_PORT === 22)
  {
    $do_ssh = TRUE;
  }
  elseif ($sd->SD_MANAGEMENT_PORT === 23)
  {
    $do_ssh = FALSE;
  }
  else
  {
    if ($default_protocol === "ssh")
    {
      $do_ssh = TRUE;
    }
    else
    {
      $do_ssh = FALSE;
    }
  }

  try {
    if ($do_ssh)
    {
      $sms_sd_ctx = new deviceSshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
      $sms_sd_ctx->setParam("PROTOCOL", "SSH");
    }
    else
    {
      $sms_sd_ctx = new deviceTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
      $sms_sd_ctx->setParam("PROTOCOL", "TELNET");
    }
  }
  catch (SmsException $e)
  {
    try
    {
      $sms_sd_ctx = new deviceTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd);
      $sms_sd_ctx->setParam("PROTOCOL", "TELNET");
    }
    catch (SmsException $e)
    {
      return ERR_SD_CONNREFUSED;
    }
  }

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


class deviceSshConnection extends SshConnection
{

  public function do_post_connect()
  {
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

    sendexpectone(__FILE__.':'.__LINE__, $this, "conf", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "terminal length 0", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "terminal width 512", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "exit",'#');
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


class deviceTelnetConnection extends TelnetConnection
{

  public function do_connect()
  {
    if (!empty($this->sd_management_port) && $this->sd_management_port != 22)
    {
      parent::connect("telnet {$this->sd_ip_config} $this->sd_management_port");
    }
    else
    {
      parent::connect("telnet {$this->sd_ip_config}");
    }

    unset($tab);
    $tab[0] = ">";
    $tab[1] = "#";
    $tab[2] = "login:";
    $tab[3] = "assword:";
    $tab[4] = "ress any key to continue";

    $index = 99;
    $login_state = 0;

    for ($i = 1; ($i <= 20) && ($login_state < 4); $i++)
    {
      switch ($index)
      {
        case -1:
          do_disconnect();
          throw new SmsException("$origin: connection error for {$this->sd_ip_config}", ERR_SD_TIMEOUTCONNECT);

        case 99: // wait for router
          $index = $this->expect(__FILE__.':'.__LINE__, $tab);
          switch ($login_state)
          {
            case 1:
              if ($index === 2)
              {
                do_disconnect();
                throw new SmsException("$origin: connection error for {$this->sd_ip_config}",  ERR_SD_AUTH);
              }
              break;

            case 2:
              if ($index > 1)
              {
                do_disconnect();
                throw new SmsException("$origin: connection error for {$this->sd_ip_config}",  ERR_SD_AUTH);
              }
              break;

            case 3:
              if ($index !== 1)
              {
                do_disconnect();
                throw new SmsException("$origin: connection error for {$this->sd_ip_config}",  ERR_SD_ADM_AUTH);
              }
              break;
          }
          break;

        case 0: // ">"
          sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, "enable", "assword:");
          $this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
          $index = 99;
          $login_state = 3;
          break;

        case 1: // "#"
          $login_state = 4;
          break;

        case 2: // "Username"
          //$this->sendCmd(__FILE__.':'.__LINE__,  "{$this->sd_login_entry}");
          $this->send(__FILE__.':'.__LINE__,  "{$this->sd_login_entry}\r");
          $index = 99;
          $login_state = 1;
          break;

        case 3: // "password"
          //$this->sendCmd(__FILE__.':'.__LINE__,  "{$this->sd_passwd_entry}");
          $this->send(__FILE__.':'.__LINE__,  "{$this->sd_passwd_entry}\r");
          $index = 99;
          $login_state = 2;
          break;

        case 4:
          $this->sendCmd(__FILE__.':'.__LINE__, " ");//send a blank command
          $index = 99;
          $login_state = 0;
          break;
      }
    }

    echo "Telnet connection established to {$this->sd_ip_config}\n";
    $this->do_post_connect();
    $this->do_store_prompt();
    $this->do_start();
  }

  public function do_post_connect()
  {
    $this->sendCmd(__FILE__.':'.__LINE__, '');
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

    sendexpectone(__FILE__.':'.__LINE__, $this, "conf", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "terminal length 0", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "terminal width 512", '(config)#');
    sendexpectone(__FILE__.':'.__LINE__, $this, "exit",'#');
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
