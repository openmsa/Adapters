<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('nfvo_generic', 'nfvo_generic_connect.php');
require_once load_once('nfvo_generic', 'adaptor.php');

function nfvo_generic_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	return sd_connect();
}

