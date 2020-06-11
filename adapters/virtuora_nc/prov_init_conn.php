<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  virtuora_nc_connect($ipaddr, $login, $passwd);
  virtuora_nc_disconnect();

  return SMS_OK;
}

?>