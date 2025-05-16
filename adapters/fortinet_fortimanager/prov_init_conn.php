<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  fortinet_fortimanager_connect($ipaddr, $login, $passwd);
  fortinet_fortimanager_disconnect();

  return SMS_OK;
}

?>