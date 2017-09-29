<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
function openstack_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;

  $url = "curl --connect-timeout 15 --max-time 15 -X DELETE http://".$ipaddr;
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