<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------

require_once load_once('aws_generic', 'aws_generic_connect.php');
require_once load_once('aws_generic', 'adaptor.php');

function aws_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $sms_sd_ctx;
  $ret = sd_connect();
  return $ret;
}

?>