<?php

// -------------------------------------------------------------------------------------
// INITIAL CONNECTION
// -------------------------------------------------------------------------------------
function prov_init_conn($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $ipaddr;
  global $login;
  global $passwd;

  $ret = sd_connect($ipaddr, $login, $passwd);
  if ($ret != SMS_OK)
  {
    return $ret;
  }
  sd_disconnect();

  return SMS_OK;
}

?>