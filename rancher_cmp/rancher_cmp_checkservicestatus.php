<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('rancher_cmp', 'rancher_cmp_connect.php');
require_once load_once('rancher_cmp', 'adaptor.php');

function rancher_cmp_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>