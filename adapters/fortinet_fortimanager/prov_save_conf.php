<?php

// -------------------------------------------------------------------------------------
// SAVE CONFIGURATION
// -------------------------------------------------------------------------------------
function prov_save_conf($sms_csp, $sdid, $sms_sd_info, &$err)
{
  return sd_commit_conf(__FILE__.':'.__LINE__, $sdid, 'Initial Provisioning Backup', 'CONF_FILE', $err);
}

?>