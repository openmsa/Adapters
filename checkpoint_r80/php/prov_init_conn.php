<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  checkpoint_r80_connect($ipaddr, $login, $passwd);
  checkpoint_r80_disconnect();

  return SMS_OK;
}

?>