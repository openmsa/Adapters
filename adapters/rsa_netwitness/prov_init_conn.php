<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  rsa_netwitness_connect($ipaddr, $login, $passwd);
  rsa_netwitness_disconnect();

  return SMS_OK;
}

?>