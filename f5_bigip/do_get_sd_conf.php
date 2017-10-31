<?php

/*
 * Available global variables
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_sd_info sd_info structure
 * $sdid
 * $sms_module module name (for patterns)
 * $SMS_RETURN_BUF string buffer containing the result
 */

// Get router configuration, not JSON response format
require_once 'smsd/sms_common.php';

require_once load_once('f5_bigip', 'f5_bigip_connect.php');
require_once load_once('f5_bigip', 'f5_bigip_configuration.php');

try {
    $ret = f5_bigip_connect();
    if ($ret !== SMS_OK) {
        throw new SmsException("", ERR_SD_CONNREFUSED);
    }

    // Get the conf on the router
    $conf = new f5_bigip_configuration($sdid);
    $SMS_RETURN_BUF = $conf->get_running_conf();
    f5_bigip_disconnect();
} catch (Exception $e) {
    f5_bigip_disconnect();
    return $e->getCode();
}

return SMS_OK;
?>
