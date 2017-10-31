<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_sd_info pointer to sd_info structure
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';
require_once load_once('f5_bigip', 'f5_bigip_connect.php');
require_once load_once('f5_bigip', 'apply_errors.php');
require_once "$db_objects";

function f5_bigip_apply_conf($configuration) {
    global $sdid;
    global $sms_sd_ctx;
    global $sms_sd_info;
    global $sendexpect_result;
    global $apply_errors;
    global $SMS_OUTPUT_BUF;
    global $SD;
    $delay = 50000;

    if (strlen($configuration) === 0) {
        return SMS_OK;
    }

    save_result_file($configuration, "conf.applied");

    $SMS_OUTPUT_BUF = '';

    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "tmsh", "(tmos)#");
    $line = get_one_line($configuration);
    while ($line !== false) {
        $line = trim($line);
        if (! empty($line)) {
            $SMS_OUTPUT_BUF .= $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $line, "(tmos)#", $delay);
        }
        $line = get_one_line($configuration);
    }
    $SMS_OUTPUT_BUF .= $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "quit", "#");
    save_result_file($SMS_OUTPUT_BUF, "conf.error");

    foreach ($apply_errors as $apply_error) {
        if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0) {
            $SMS_OUTPUT_BUF = "$apply_error\n" . $SMS_OUTPUT_BUF;
            $SMS_OUTPUT_BUF = preg_replace('/ [\x08]/', '', $SMS_OUTPUT_BUF);
            sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
            return ERR_SD_CMDFAILED;
        }
    }
    $SMS_OUTPUT_BUF = preg_replace('/ [\x08]/', '', $SMS_OUTPUT_BUF);
    $SMS_OUTPUT_BUF = str_replace("\n", "\\n", $SMS_OUTPUT_BUF);
    return SMS_OK;
}

?>

