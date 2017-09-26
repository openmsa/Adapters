<?php

// -------------------------------------------------------------------------------------
// UPDATE LICENSE
// -------------------------------------------------------------------------------------
function prov_update_license($sms_csp, $sdid, $sms_sd_info, &$err)
{
  $conf = new wsa_configuration($sdid, true);

  return $conf->update_license();
}

?>