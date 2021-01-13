<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new ovm_manager_configuration($sdid, true);

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  ovm_manager_disconnect();
  return SMS_OK;
}

?>