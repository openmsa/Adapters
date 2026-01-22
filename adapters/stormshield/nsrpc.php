<?php
/*
 * Version: $Id: netasq_configuration.php 24140 2009-11-24 14:46:35Z tmt $
 * Created: Feb 12, 2009
 */

require_once 'smsd/sms_common.php';

/*
 * Return code
 Success (>= 100, < 110)
   100 command successful
   101 command successful, data follow
   102 command successful, waiting for data
   103 command successful, disconnecting
   104 command successful, reboot needed
 Warning (>= 110, < 200)
   110 command successful, warning
   111 command successful, multiple warnings
 Error (>= 200)
   200 command error
   201 return error message on many lines
   203 authentication failed
   203 client is idle, disconnecting
   204 maximum number of authentication user reached for that level
   205 not enough privilege
   206 licence restriction
 */

function get_return_codes(&$nsrpc_output)
{
  define("RC_LEN", 3);

  $rc_list = array();

  $pos = 0;
  do
  {
    $end = true;
    $pos = strpos($nsrpc_output, ' code=', $pos);
    if ($pos !== false)
    {
      $pos = $pos - RC_LEN;
      if ($pos < 0)
      {
        return $rc_list;
      }
      if (($pos === 0) || ($nsrpc_output[$pos-1] === "\n"))
      {
        // This line contains a return code
        $rc = substr($nsrpc_output, $pos, RC_LEN);
        $rc_list[$rc] = $rc;
      }
      // Get next code if any
      $pos += RC_LEN + 6; // 'xxx code='
      $end = false;
    }
  }
  while (!$end);

  return $rc_list;
}

/*
 * $nsrpc_output string to check looks like
 * 100 code=00a00100 msg="Ok"
 * or
 * 102 code=00a00300 msg="Waiting for data"
 * or
 * 200 code=00100800 msg="Error in format"
 *
 * other format should not be taken into account
 */
function is_error(&$nsrpc_output)
{
  // return code considered as ok
  $ok_return_code = array (
    "100" => true,
    "103" => true,
    "104" => true,
    "110" => true,
    "111" => true,
  );

  // intermediate return code, it is normaly followed by another return code
  $not_a_return_code = array (
    "101" => true,
    "102" => true,
  );

  $rc_list = get_return_codes($nsrpc_output);

  foreach ($rc_list as $rc)
  {
    if (empty($ok_return_code[$rc]) && empty($not_a_return_code[$rc]))
    {
      return true;
    }
    if (empty($not_a_return_code[$rc]))
    {
      return false;
    }
  }

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

