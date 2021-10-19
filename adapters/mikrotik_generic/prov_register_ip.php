<?php

// -------------------------------------------------------------------------------------
// DNS & IP CONFIG UPDATE
// -------------------------------------------------------------------------------------
function prov_register_ip($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;

  return sd_ip_update($sms_csp, $sdid, $sms_sd_info, $ipaddr);
}

?>