<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $is_ztd;

  $conf = new citrix_netscalar_configuration($sdid, true);

  if ($is_ztd)
  {
    $conf->is_ztd = true;
  }

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  citrix_netscalar_disconnect();
  return SMS_OK;
}

?>