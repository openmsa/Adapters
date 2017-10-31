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

  return a10_thunder_connect($ipaddr, $login, $passwd, $adminpasswd, $port);
}

?>