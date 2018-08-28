<?php

// -------------------------------------------------------------------------------------
// LOCK PROVISIONING
// -------------------------------------------------------------------------------------
function prov_lock($sms_csp, $sdid, $sms_sd_info, $stage)
{
  sms_sd_prov_unlock($sms_csp, $sms_sd_info);
  $ret = sms_sd_prov_lock($sms_csp, $sms_sd_info);
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }
  return SMS_OK;
}

?>