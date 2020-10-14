<?php

// -------------------------------------------------------------------------------------
// DNS & IP CONFIG UPDATE
// -------------------------------------------------------------------------------------
function prov_dns_update($sms_csp, $sdid, $sms_sd_info, &$err)
{
  return sd_ip_update($sms_csp, $sdid, $sms_sd_info, $ipaddr);
}

?>