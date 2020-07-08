<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('kubernetes_generic', 'kubernetes_generic_connect.php');
require_once load_once('kubernetes_generic', 'adaptor.php');

function kubernetes_generic_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>