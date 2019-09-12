<?php

// Generate the Pre-Conf for the router
require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once "$db_objects";

require_once load_once('a10_thunder', 'a10_thunder_configuration.php');

try {
    $conf = new a10_thunder_configuration($sdid);
    $configuration = $conf->get_staging_conf();

    $result = sms_user_message_add("", SMS_UMN_CONFIG, $configuration);
    $user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
    $user_message = sms_user_message_add_json($user_message, SMS_UMN_RESULT, $result);

    sms_send_user_message($sms_csp, $sdid, $user_message);
} catch (Exception | Error $e) {
    sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;

?>
