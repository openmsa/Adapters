<?php

// -------------------------------------------------------------------------------------
// DNS & IP CONFIG UPDATE
// -------------------------------------------------------------------------------------
function prov_dns_update($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $ipaddr;

  // Set Ip config
  $ret = sms_bd_set_ipconfig($sms_csp, $sms_sd_info, $ipaddr);
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }

  // DNS Update
  $ret = dns_update($sdid, $ipaddr);
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }
  return SMS_OK;
}

?>
