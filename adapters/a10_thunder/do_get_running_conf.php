<?php

// Get router configuration
require_once 'smsd/sms_common.php';

require_once load_once('a10_thunder', 'a10_thunder_connect.php');
require_once load_once('a10_thunder', 'a10_thunder_configuration.php');

try {
    $ret = a10_thunder_connect();
    if ($ret !== SMS_OK) {
        sms_send_user_error($sms_csp, $sdid, "", ERR_SD_CONNREFUSED);
        return SMS_OK;
    }

    // Get the conf on the router
    $conf = new a10_thunder_configuration($sdid);
    $running_conf = $conf->get_running_conf();
    a10_thunder_disconnect();

    if (! empty($running_conf)) {
        sms_send_user_ok($sms_csp, $sdid, $running_conf);
    } else {
        sms_send_user_error($sms_csp, $sdid, "", ERR_SD_FAILED);
    }
} catch (Exception | Error $e) {
    sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;

?>
