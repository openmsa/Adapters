<?php

require_once load_once('zscaler', 'connect.php');

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  connect($ipaddr, $login, $passwd);
  disconnect();

  return SMS_OK;
}

?>