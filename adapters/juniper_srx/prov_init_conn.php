<?php

require_once "$db_objects";

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;
  global $port;


//  if (isset($sd->SD_CONFIGVAR_list['CUSTOM_MNGT_IP'])) {
//    $ipaddr = trim($sd->SD_CONFIGVAR_list['CUSTOM_MNGT_IP']->VAR_VALUE);
//    echo "CUSTOM_MNGT_IP: using custome management port: " . $ipaddr . "\n";
//  } 

  /**
   *
   * Wait for a device to be up (ssh prompt)
   * @param string $ip_addr IP address of the device to wait
   */
  // wait the device become up after boot
  $done = 20;
  $ret = "SMS_ERROR";
  do
  {
    echo "waiting for the device, $done\n";
    sleep(5);
    unset($ret);
    $ret = exec_local(__FILE__ . ':' . __LINE__, "echo quit | telnet {$ipaddr}  {$port}  2>/dev/null | grep Connected", $output);

    if ($ret == SMS_OK)
    {
      if (strpos($output[0], "Connected") === 0)
      {
        $ret = juniper_srx_connect($ipaddr, $login, $passwd, $port);
        break;
      }
    }
    $done--;
  } while ($done > 0);

  if (($done === 0) || ($ret != SMS_OK))
  {
    return ERR_SD_CMDTMOUT;
  }
  return SMS_OK;
}

?>