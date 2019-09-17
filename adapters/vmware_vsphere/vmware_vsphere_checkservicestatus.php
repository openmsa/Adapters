<?php

// -------------------------------------------------------------------------------------
// CHECK SERVICE STATUS
// -------------------------------------------------------------------------------------
require_once load_once('vmware_vsphere', 'vmware_vsphere_connect.php');
require_once load_once('vmware_vsphere', 'adaptor.php');

function vmware_vsphere_checkservicestatus($sms_csp, $sdid, $sms_sd_info, &$err)
{
	global $sms_sd_ctx;
	$ret = sd_connect();
	return $ret;
}

?>