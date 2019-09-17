<?php

// -------------------------------------------------------------------------------------
// DISCONNECTION
// -------------------------------------------------------------------------------------
function prov_disconnect($sms_csp, $sdid, $sms_sd_info, &$err)
{
  return fortinet_generic_disconnect();
}

?>