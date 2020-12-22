<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new vmware_ovm_configuration($sdid, true);

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  vmware_ovm_disconnect();
  return SMS_OK;
}

?>