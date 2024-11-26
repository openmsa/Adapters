<?php
/*
 * Version: $Id$
 * Created: Jun 26, 2015
 * Available global variables
 *  $sms_csp           Pointer to csp context to send response to user
 *  $sdid              Device ID for which the log files have to be listed
 *  $optional_params   Contains start and end dates
 */

require_once "smsd/sms_common.php";
require_once "$db_objects";

$network = get_network_profile();
$SD = &$network->SD;

$sd_ip_config = $SD->SD_IP_CONFIG;
$sd_login_entry = $SD->SD_LOGIN_ENTRY;
$sd_passwd_entry = $SD->SD_PASSWD_ENTRY;

$params = preg_split("#\r\n#", $optional_params);
$msg_id = trim($params[0]);

if (!empty($msg_id)) {
    //get authenticated to the device and save the cookies
    $login_url = "https://$sd_ip_config/logincheck";
    $login_post = "username=$sd_login_entry&secretkey=$sd_passwd_entry";

    $cookie_file = "/tmp/cookies_" . $sd_ip_config . ".txt";

    $login_cmd = "curl -s -c $cookie_file -k -d '$login_post' $login_url";
    echo $login_cmd . "\n";
    $ret = exec_local(__FILE__ . ":" . __LINE__, $login_cmd, $output_array);
    if ($ret !== 0 || !file_exists($cookie_file)) {
        if (file_exists($cookie_file)) {
            unlink($cookie_file);
        }
        return ERR_SD_ADM_AUTH;
    }

    //if cookies saved successfully - get the detail log
    $get_log_url = "https://$sd_ip_config/api/v2.0/log/logaccess.attackdetail?is_aggregate=1&logType=3&logfile=alog.log&msgID=$msg_id";
    //$output_file = "/opt/sms/routerlogs/$sdid/getLogByID_" . rand(100000, 999999).".json";
    $output_file = "/opt/sms/routerlogs/" . $sdid . "_getLogByID";

    $get_log_cmd = "curl -s -b $cookie_file -k -o $output_file '$get_log_url'";
    $ret = exec_local(__FILE__ . ":" . __LINE__, $get_log_cmd, $output_array);
    if ($ret === 0 && file_exists($output_file)) {
        $detail_json = file_get_contents($output_file);
        //echo "RESULT JSON ============================>".$detail_log."\n";
    }
    unlink($cookie_file);
    unlink($output_file);
    if (!$detail_json || $detail_json == "nulldata") {
        return ERR_LOCAL_CMD;
    }
    if (!preg_match("/msg_id/", $detail_json, $match)) {
        sms_send_user_error(
            $sms_csp,
            $sdid,
            "msg_id not found",
            ERR_VERB_BAD_PARAM
        );
        sms_close_user_socket($sms_csp);
        return SMS_OK;
    }
}

if ($ret !== 0) {
    sms_send_user_error($sms_csp, $sdid, "", $ret);
} else {
    sms_send_user_ok($sms_csp, $sdid, $detail_json);
    //	sms_send_user_ok($sms_csp, $sdid, $output_file);
}
sms_close_user_socket($sms_csp);
return SMS_OK;

?>
