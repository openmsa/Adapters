<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_ztd_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new cisco_isr_configuration($sdid, true);

  $ret = $conf->provisioning(true);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }
  $optional_params="";
  $ret = $conf->reboot("REBOOT", $optional_params);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }
  
  cisco_isr_disconnect();
  return SMS_OK;
}

?>
