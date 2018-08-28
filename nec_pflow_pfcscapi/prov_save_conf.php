<?php

// -------------------------------------------------------------------------------------
// SAVE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_save_conf($sms_csp, $sdid, $sms_sd_info, $stage)
{
  $ret = exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/save_router_conf \"update\" $sdid CONF_FILE", $output);
  if ($ret !== SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }

  return SMS_OK;
}

?>