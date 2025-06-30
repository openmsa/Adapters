<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('openstack_generic', 'openstack_generic_connect.php');
require_once load_once('openstack_generic', 'adaptor.php');

function openstack_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>