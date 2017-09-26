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
  public function do_connect()
  {
    try
    {
      $this->setParam('PROTOCOL', 'SSH');
      parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
    }
    catch (SmsException $e)
    {
      // try the alternate port
      if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback))
      {
        try
        {
          parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        }
        catch (SmsException $e)
        {
          $this->setParam('PROTOCOL', 'TELNET');
          parent::connect("telnet '{$this->sd_ip_config}'");
        }
      }
      else
      {
        $this->setParam('PROTOCOL', 'TELNET');
        parent::connect("telnet '{$this->sd_ip_config}'");
      }
    }
  }

  public function do_start()
  {
    $this->setParam('suppress_echo', true);
  }

  public function sendCmd($origin, $cmd)
  {
    return $this->send($origin, "$cmd\n");
  }

  // extract the prompt
  function extract_prompt()
  {
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'conf t', '(config)#');
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'exit', '#');
    $buffer = trim($buffer);
    if ($this->getParam('CONTEXT_MODE') === "MULTI")
    {
      $this->setPrompt('#');
    }
    else
    {
      /* for synchronization */
      $buffer = substr(strrchr($buffer, "\n"), 1); // get the last line
      $this->setPrompt($buffer);
    }
  }
}

// return false if error, true if ok
function device_connect($login = null, $passwd = null, $adminpasswd = null, $sd_ip_addr = null, $port_to_use = null)
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

  $index = 99;
  $login_state = 0;

  for ($i = 1; ($i <= 20) && ($login_state < 4); $i++)
  {
    switch ($index)
    {
      case -1:
        device_disconnect();
        throw new SmsException("connection error for {$sd_ip_addr}", ERR_SD_TIMEOUTCONNECT, __FILE__ . ':' . __LINE__);

      case 99: // wait for router
        $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
        switch ($login_state)
        {
          case 1:
            if ($index === 2)
            {
              device_disconnect();
              throw new SmsException("connection error for {$sd_ip_addr}", ERR_SD_AUTH, __FILE__ . ':' . __LINE__);
            }
            break;

          case 2:
            if ($index > 1)
            {
              device_disconnect();
              throw new SmsException("connection error for {$sd_ip_addr}", ERR_SD_AUTH, __FILE__ . ':' . __LINE__);
            }
            break;

          case 3:
            if ($index !== 1)
            {
              device_disconnect();
              throw new SmsException("connection error for {$sd_ip_addr}", ERR_SD_ADM_AUTH, __FILE__ . ':' . __LINE__);
            }
            break;
        }
        break;

      case 0: // ">"
        sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "enable", "assword:");
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $adminpasswd);
        $index = 99;
        $login_state = 3;
        break;

      case 1: // "#"
        $login_state = 4;
        break;

      case 2: // "Username"
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $login);
        $index = 99;
        $login_state = 1;
        break;

      case 3: // "password"
        echo "Sending Password $passwd\n";
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $passwd);
        $index = 99;
        $login_state = 2;
        break;
    }
  }

  // detect if multi context mode
  //command : sho mode
  //answer  : Security context mode: multiple
  // this command may not working on device that cannot support multi-mode
  unset($tab);
  $tab[0] = "multiple";
  $tab[1] = "ERROR";
  $tab[2] = "#";
  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'sho mode');
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
  switch ($index)
  {
    case 0: //multi mode
      $sms_sd_ctx->setParam('CONTEXT_MODE', 'MULTI');
      break;

    case 1: // not supported => mono mode
      $sms_sd_ctx->setParam('CONTEXT_MODE', 'MONO');
      break;

    case 2: // mono mode
      $sms_sd_ctx->setParam('CONTEXT_MODE', 'MONO');
      break;
  }
  echo "Context mode :" . $sms_sd_ctx->getParam('CONTEXT_MODE') . "\n";
  $sms_sd_ctx->extract_prompt();

  sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "terminal pager 0");

  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function device_disconnect($clean_exit = false)
{
  global $sms_sd_ctx;
  global $sdid;

  if (!isset($sms_sd_ctx))
  {
    echo "device_disconnect => already disconnected\n";
    return;
  }

  if ($clean_exit)
  {
    // Exit from config mode
    unset($tab);
    $tab[0] = $sms_sd_ctx->getPrompt();
    $tab[1] = ")#";
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
    $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
    for ($i = 1; ($i <= 10) && ($index === 1); $i++)
    {
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'exit');
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
    }
  }

  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

