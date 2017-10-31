<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;
  global $port;

  return nec_intersecvmsg_connect($ipaddr, $login, $passwd, null, '18022');
}

?>