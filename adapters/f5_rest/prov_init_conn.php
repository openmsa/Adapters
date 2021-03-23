<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  f5_rest_connect($ipaddr, $login, $passwd);
  f5_rest_disconnect();

  return SMS_OK;
}

?>