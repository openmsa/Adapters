<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('juju_webapp', 'juju_webapp_connect.php');
require_once load_once('juju_webapp', 'adaptor.php');

function juju_webapp_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	return sd_connect();
}

