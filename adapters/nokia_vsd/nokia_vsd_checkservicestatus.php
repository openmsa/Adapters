<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('nokia_vsd', 'nokia_vsd_connect.php');
require_once load_once('nokia_vsd', 'adaptor.php');

function nokia_vsd_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>