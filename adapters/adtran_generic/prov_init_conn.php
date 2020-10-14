<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;
  global $adminpasswd;
  global $port;

  return adtran_generic_connect($ipaddr, $login, $passwd, $adminpasswd, $port);
}

?>