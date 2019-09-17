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

require_once load_once('a10_thunder', 'a10_thunder_connect.php');
require_once load_once('a10_thunder', 'a10_thunder_configuration.php');

try {
    $ret = a10_thunder_connect();
    if ($ret !== SMS_OK) {
        throw new SmsException("", ERR_SD_CONNREFUSED);
    }

    // Get the conf on the router
    $conf = new a10_thunder_configuration($sdid);
    $SMS_RETURN_BUF = $conf->get_running_conf();
    a10_thunder_disconnect();
} catch (Exception | Error $e) {
    a10_thunder_disconnect();
    return $e->getCode();
}

return SMS_OK;
?>
