<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $is_ztd;

  $conf = new juniper_srx_configuration($sdid, true);

  if ($is_ztd)
  {
    $conf->is_ztd = true;
  }

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  juniper_srx_disconnect();
  return SMS_OK;
}

?>