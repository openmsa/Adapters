<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new AristaEosConfiguration($sdid);

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  arista_eos_disconnect();
  return SMS_OK;
}

?>