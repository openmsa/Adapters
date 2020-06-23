<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  redfish_generic_connect($ipaddr, $login, $passwd);
  redfish_generic_disconnect();

  return SMS_OK;
}

?>