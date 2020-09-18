<?php

// -------------------------------------------------------------------------------------
// DISCONNECTION
// -------------------------------------------------------------------------------------
function prov_disconnect($sms_csp, $sdid, $sms_sd_info, &$err)
{
  return mikrotik_generic_disconnect();
}

?>
