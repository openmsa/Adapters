<?php

// -------------------------------------------------------------------------------------
// UNLOCK PROVISIONING
// -------------------------------------------------------------------------------------
function prov_unlock($sms_csp, $sdid, $sms_sd_info, $stage)
{
  $ret = sms_sd_prov_unlock($sms_csp, $sms_sd_info);
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }
  sms_sd_forceasset($sms_csp, $sms_sd_info);
  return SMS_OK;
}

?>