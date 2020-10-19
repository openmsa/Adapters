<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('nokia_cloudband', 'nokia_cloudband_connect.php');
require_once load_once('nokia_cloudband', 'adaptor.php');

function nokia_cloudband_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>