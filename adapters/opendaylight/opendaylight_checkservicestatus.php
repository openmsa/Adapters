<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
function opendaylight_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $port;

  $url = "curl --connect-timeout 15 --max-time 15 http://".$ipaddr.":".$port."/index.html";
  $test_availibility = shell_exec($url) ;
  if(strlen($test_availibility)>200)
  {
    return SMS_OK;
  }
  else
  {
    $err = $test_availibility;
    return ERR_SD_FAILED;
  }
}

?>