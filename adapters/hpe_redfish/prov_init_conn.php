<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  hpe_redfish_connect($ipaddr, $login, $passwd);
  hpe_redfish_disconnect();

  return SMS_OK;
}

?>