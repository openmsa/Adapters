<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_sd_info pointer to sd_info structure
 * $SMS_RETURN_BUF string buffer containing the result
 */

// Transfer the configuration file on the router
require_once 'smsd/sms_common.php';
require_once load_once('a10_thunder', 'a10_thunder_connect.php');
require_once load_once('a10_thunder', 'apply_errors.php');
require_once "$db_objects";

function a10_thunder_apply_conf($configuration) {
    global $sdid;
    global $sms_sd_ctx;
    global $sms_sd_info;
    global $sendexpect_result;
    global $apply_errors;
    global $SMS_OUTPUT_BUF;
    global $SD;

    if (strlen($configuration) === 0) {
        return SMS_OK;
    }

    save_result_file($configuration, "conf.applied");

    $SMS_OUTPUT_BUF = '';

    $tab[0] = ')#';
    $tab[1] = 'yes/no';
    // config mode
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "configure terminal", $tab);
    if ($index == 1) {
        $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "yes", ")#");
    }
	
    unset($tab);
    $tab[0] = ')#';
    $tab[1] = 'y/n)';
    $line = get_one_line($configuration);
    while ($line !== false) {
        $line = trim($line);
        if (! empty($line)) {
            $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, $tab);
            $SMS_OUTPUT_BUF .= $sendexpect_result;
            if( $index == 1){
                $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'y', ')#');
                $SMS_OUTPUT_BUF .= $sendexpect_result;
            }
        }
        $line = get_one_line($configuration);
    }
    $SMS_OUTPUT_BUF .= $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "end", "#");

    save_result_file($SMS_OUTPUT_BUF, "conf.error");

    foreach ($apply_errors as $apply_error) {
        if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0) {
            $SMS_OUTPUT_BUF = "$apply_error\n" . $SMS_OUTPUT_BUF;
            $SMS_OUTPUT_BUF = preg_replace('/[\x00-\x08]/', '', $SMS_OUTPUT_BUF);
            sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
            return ERR_SD_CMDFAILED;
        }
    }

    $SMS_OUTPUT_BUF = preg_replace('/[\x00-\x08]/', '', $SMS_OUTPUT_BUF);
    $SMS_OUTPUT_BUF = str_replace("\n", "\\n", $SMS_OUTPUT_BUF);
    $SMS_OUTPUT_BUF = str_replace('"', '\"', $SMS_OUTPUT_BUF);

    return SMS_OK;
}

?>

