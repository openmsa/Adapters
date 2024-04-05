<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------

require_once load_once('aws_global', 'aws_global_connect.php');
require_once load_once('aws_global', 'adaptor.php');

function aws_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
  global $sms_sd_ctx;
  $ret = sd_connect();
  return $ret;
}

?>