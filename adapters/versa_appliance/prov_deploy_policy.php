<?php

// -------------------------------------------------------------------------------------
// SAVE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_deploy_policy($sms_csp, $sdid, $sms_sd_info, $stage)
{
  $ret = SMS_OK;
  if ($ret !== SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }

  return SMS_OK;
}

?>