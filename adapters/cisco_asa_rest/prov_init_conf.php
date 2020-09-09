<?php

// -------------------------------------------------------------------------------------
// UPDATE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_init_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new cisco_asa_rest_configuration($sdid, true);

  $ret = $conf->provisioning();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  cisco_asa_rest_disconnect();
  return SMS_OK;
}

?>