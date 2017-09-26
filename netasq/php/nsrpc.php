<?php
/*
 * Version: $Id: netasq_configuration.php 24140 2009-11-24 14:46:35Z tmt $
 * Created: Feb 12, 2009
 */

require_once 'smsd/sms_common.php';

/*
 * Return code
 Success
  100 command successful
  101 command successful, download follow
  102 command successful, upload follow
  103 command successful, you will be disconnected
  104 command successful, but reboot needed
 Warning
  110 command successful, but warning
  111 command successful, but multiple warnings
 Error
  200 command error
  201 return error message on many lines
  203 authentication failed
  203 client is idle, disconnecting
  204 maximum number of authentication user reached for that level
  205 not enough privilege
  206 licence restriction
*/

// code retour considere comme ok
$ok_return_code = array (
    "100" => true,
    "103" => true,
    "104" => true,
    "110" => true,
    "111" => true,
);

// code retour intermediaire, pas vraiment un code retour, mais pas une erreur
$not_a_return_code = array (
    "101" => true,
    "102" => true,
);

// command exception must not be considered as an error, a workaround of not wanted behavior of the device (bug ?)
$cmd_exception = array (
	"ha reboot serial=passive" => "code=01700100",
);

define("RC_LEN", 3);



/** nsrpc tool wrapper */
class nsrpc
{
  // Liste ordonnée ...
  private $actions_list;
  private $sd;
  private $thread_id;

  function __construct(&$sd)
  {
    $this->actions_list = array();
    $this->sd = &$sd;
    $this->thread_id = $_SERVER['THREAD_ID'];
  }

  function clean_pool()
  {
    unset($this->actions_list);
  }

  function add_action($action)
  {
    $this->actions_list[] = $action;
    echo "action [" . $action . "] added.\n";
  }

  function execute_pool(&$output)
  {
    global $ok_return_code;
    global $not_a_return_code;

    echo "Entering execute_pool()\n";

    // Create a backup script file
    $nsrpc_script_file = "/opt/sms/spool/tmp/nsrpc_script_{$this->thread_id}";

    echo "nsrpc script: " . $nsrpc_script_file . "\n";

    if (is_file($nsrpc_script_file))
    {
      // Remove any previous file
      unlink($nsrpc_script_file);

      // Assert no file of the name exist
      if (is_file($nsrpc_script_file))
      {
        $err_msg = "Can't delete the file {$nsrpc_script_file}";
        sms_log_error(__FILE__.':'.__LINE__.": {$err_msg}\n");
        return ERR_LOCAL_FILE;
      }
    }

    // Create the file
    $handle = fopen($nsrpc_script_file, "w");

    foreach ($this->actions_list as $action)
    {
      echo "Action [" . $action . "]\n";
      $ret = fputs($handle, "$action\n");
      if ($ret === false)
      {
        $err_msg = "Writing [$action] in file [$nsrpc_script_file] has failed!";
        sms_log_error(__FILE__.':'.__LINE__.": {$err_msg}\n");
        fclose($handle);
        unlink($nsrpc_script_file);
        return ERR_LOCAL_FILE;
      }
    }
    fclose($handle);

    echo "Deploy now the config file ($nsrpc_script_file) into the router\n";

    // date +\"%Y/%m/%d:%H:%M:%S\" >> /opt/sms/logs/nsrpc.log &&  -l /opt/sms/logs/nsrpc.log
    $cmd = "cd /opt/sms/bin/netasq 2>&1 && ./nsrpc -c $nsrpc_script_file '{$this->sd->SD_LOGIN_ENTRY}:{$this->sd->SD_PASSWD_ENTRY}@{$this->sd->SD_IP_CONFIG}' 2>&1";
    $ret = exec_local(__FILE__.':'.__LINE__, $cmd, $output);
    if ($ret !== SMS_OK)
    {
      $err_msg = "Command [$cmd] has failed!";
      sms_log_error(__FILE__.':'.__LINE__.": {$err_msg}\n");
      unlink($nsrpc_script_file);
      return $ret;
    }

    unlink($nsrpc_script_file);

    // for each action check the return code
    // ouput contains the actions and their return
    $index_action = 0;
    $last_index_action = 0;
    $nb_action = count($this->actions_list);
    $error = false;
    foreach ($output as $index => $line)
    {
      echo "nsrpc output ($index) : $line\n";

      if (($index_action < $nb_action) && strpos($line, $this->actions_list[$index_action]))
      {
        $last_index_action = $index_action;
        $index_action++;
      }
      else
      {
        if (is_error($line, $this->actions_list[$last_index_action]))
        {
          $err_msg = "Command [{$this->actions_list[$last_index_action]}] has failed : $line";
          sms_log_error(__FILE__.':'.__LINE__.": {$err_msg}\n");
          $error = true;
        }
      }
    }

    if ($error)
    {
      return ERR_SD_CMDFAILED;
    }

    return SMS_OK;
  }
}

/*
 * $buffer looks like
 * 100 code=00a00100 msg="Ok"
 * or
 * 102 code=00a00300 msg="Waiting for data"
 * or
 * 200 code=00100800 msg="Error in format"
 */
function is_error(&$buffer, &$command)
{
  global $ok_return_code;
  global $not_a_return_code;
  global $cmd_exception;

  $pos = 0;
  do
  {
    $end = true;
    $pos = strpos($buffer, ' code=', $pos);
    if ($pos !== false)
    {
      // C'est une reponse, recuperer le code
      $pos = $pos - RC_LEN;
      if ($pos < 0)
      {
        return false;
      }
      $code = substr($buffer, $pos, RC_LEN);
      if (empty($ok_return_code[$code]) && empty($not_a_return_code[$code]))
      {
        foreach ($cmd_exception as $cmd => $rc)
        {
          if ((strpos($command, $cmd) !== false) && (strpos($buffer, $rc) !== false))
          {
            return false;
          }
        }
        return true;
      }
      if (empty($not_a_return_code[$code]))
      {
        return false;
      }
      // Get second code after "Waiting for data"
      $pos += 6; // ' code='
      $end = false;
    }
  }
  while (!$end);

  return false;
}

/*
 * Get the return code from $nsrpc_output for the command $cmd to see if a reboot is needed
 * Assume no error, this means is_error() is called before
 * $nsrpc_output looks like
 Welcome to Netasq Cipher/SRP client
 Connecting to 172.10.15.201...
 Using protected connection and SRP authentication.
 SerialNumber=U120XA0A0800970
 User=admin Level="modify,mon_write,base,contentfilter,log,filter,vpn,log_read,pki,object,user,admin,network,route,maintenance,asq,pvm,vpn_read,filter_read,globalobject,globalfilter" SessionLevel="base,contentfilter,log,filter,vpn,log_read,pki,object,user,admin,network,route,maintenance,asq,pvm,vpn_read,filter_read,globalobject,globalfilter"
 SRPClient> modify on force
 100 code=00a00100 msg="Ok"
 SRPClient> system licence upload < /opt/fmc_repository/License/NCM/NCMA2/NETASQ/VERSION_9/U120XA0A0800970.V9.licence
 102 code=00a00300 msg="Waiting for data"
 Sending: /opt/fmc_repository/License/NCM/NCMA2/NETASQ/VERSION_9/U120XA0A0800970.V9.licence.EOF
 100 code=00a00100 msg="Ok"
 SRPClient> system reboot
 103 code=00102700 msg="rebooting..."
 Leaving client...
 */
function is_reboot_needed(&$nsrpc_output, &$cmd, $nb_code)
{
  $pos = strpos($nsrpc_output, $cmd);
  if ($pos !== false)
  {
    for ($i = 1; $i <= $nb_code; $i++)
    {
      $pos = strpos($nsrpc_output, ' code=', $pos);
      if ($pos === false)
      {
        return true;
      }
      if ($i != $nb_code)
      {
        $pos += strlen(' code=');
      }
    }
    // recuperer le code
    $pos = $pos - RC_LEN;
    if ($pos < 0)
    {
      return true;
    }
    $code = substr($nsrpc_output, $pos, RC_LEN);
    if ($code === '104')
    {
      // reboot needed
      return true;
    }
    else
    {
      return false;
    }
  }

  return true;
}

/**
 * @}
 */

?>
