<?php

// -------------------------------------------------------------------------------------
// UNLOCK PROVISIONING
// -------------------------------------------------------------------------------------
function prov_unlock($sms_csp, $sdid, $sms_sd_info, &$err)
{
	return sd_prov_unlock($sms_csp, $sms_sd_info);
}
