<?php
/*
 * Available global variables
 * $sms_sd_info sd_info structure
 * $sms_csp pointer to csp context to send response to user
 * $sdid id of the device
 * $optional_params optional parameters
 * $sms_module module name (for patterns)
 */

// Verb JSACMD REBOOT
require_once 'smsd/sms_common.php';

require_once load_once('a10_thunder', 'a10_thunder_connect.php');
require_once load_once('a10_thunder', 'a10_thunder_configuration.php');
require_once load_once('a10_thunder', 'apply_errors.php');
require_once "$db_objects";

try {
    $status_type = 'REBOOT';

    $ret = sms_sd_lock($sms_csp, $sms_sd_info);
    if ($ret !== 0) {
        sms_send_user_error($sms_csp, $sdid, "", $ret);
        sms_close_user_socket($sms_csp);
        return SMS_OK;
    }

    sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '', '');

    // Asynchronous mode, the user socket is now closed, the results are written in database
    sms_send_user_ok($sms_csp, $sdid, "");
    sms_close_user_socket($sms_csp);

    // Connect to the device
    $ret = a10_thunder_connect();
    if ($ret !== SMS_OK) {
        sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '', $e->getMessage());
        sms_sd_unlock($sms_csp, $sms_sd_info);
        a10_thunder_disconnect();
        return SMS_OK;
    }

    $conf = new a10_thunder_configuration($sdid);
    $ret = $conf->reboot($status_type);
    a10_thunder_disconnect();
    // sms_send_user_ok($sms_csp, $sdid, "Reboot success");
} catch (Exception | Error $e) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '', $e->getMessage());
    sms_sd_unlock($sms_csp, $sms_sd_info);
    a10_thunder_disconnect();
    return SMS_OK;
}

if ($ret !== SMS_OK) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '', '');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', '', '');
sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
?>
