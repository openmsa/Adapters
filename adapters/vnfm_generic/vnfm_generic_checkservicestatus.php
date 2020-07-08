<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('vnfm_generic', 'vnfm_generic_connect.php');
require_once load_once('vnfm_generic', 'adaptor.php');

function vnfm_generic_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	return sd_connect();
}

