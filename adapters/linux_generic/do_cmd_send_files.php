<?php
/*
 * Version: $Id$
 * Created: Nov 03, 2011
 * Available global variables
 *  $sms_sd_info     sd_info structure
 *  $sms_csp         pointer to csp context to send response to user
 *  $sdid            id of the device
 *  $optional_params optional parameters
 *  $sms_module      module name (for patterns)
 *
 * example to copy files /tmp/test_push/push1* from MSA to device into /tmp/test_push2: curl -v --insecure  --header 'Accept: application/json' --header 'Authorization: Bearer xxxx' -X POST 'https://<MSA_API>/ubi-api-rest/sms/cmd/send_files/151/?params=src_dir=/tmp/test_push,file_pattern=push1*,dst_dir=/tmp/test_push2'

 */

// Verb JSACMD SDID SENDDATAFILES source_dir file_pattern destination

require_once 'smsd/sms_common.php';

require_once load_once('linux_generic', 'linux_generic_connect.php');
require_once load_once('linux_generic', 'linux_generic_configuration.php');
require_once "$db_objects";

try {
    $status_type = 'SENDDATAFILES';

    $params = preg_match("/src_dir=(.*),\s*file_pattern=(.*),\s*dst_dir=(.*)/",$optional_params,$match);
    $src_dir      = $match[1];
    $file_pattern = $match[2];
    $dst_dir      = trim($match[3]);
    sms_log_error("  Src_dir=" . $src_dir .  ", file_pattern=". $file_pattern .",dst_dir=". $dst_dir);

    if (empty($src_dir) || empty($file_pattern) || empty($dst_dir)) {
      sms_send_user_error($sms_csp, $sdid, "", ERR_VERB_BAD_PARAM . " src_dir=" . $src_dir .  ", file_pattern=". $file_pattern .",dst_dir=". $dst_dir);
      sms_close_user_socket($sms_csp);
      return SMS_OK;
    }

    $ret = sms_sd_lock($sms_csp, $sms_sd_info);
    if ($ret !== 0) {
      sms_send_user_error($sms_csp, $sdid, "", $ret);
      sms_close_user_socket($sms_csp);
      return SMS_OK;
    }

    sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

    // Asynchronous mode, the user socket is now closed, the results are written in database
    sms_send_user_ok($sms_csp, $sdid, "");
    sms_close_user_socket($sms_csp);

    // Connect to the device
    $ret = linux_generic_connect();
    if ($ret !== SMS_OK)
    {
      sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $e->getMessage());
      sms_sd_unlock($sms_csp, $sms_sd_info);
      linux_generic_disconnect();
      return SMS_OK;
    }
    $conf = new linux_generic_configuration($sdid);
    $ret = $conf->send_data_files($status_type, $src_dir, $file_pattern, $dst_dir);

    linux_generic_disconnect(true);

} catch (Exception | Error $e) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $status_message . $e->getMessage());
    sms_sd_unlock($sms_csp, $sms_sd_info);
    linux_generic_disconnect();
    return SMS_OK;
}

if ($ret !== SMS_OK) {
    sms_set_update_status($sms_csp, $sdid, int($ret), $status_type, 'FAILED', $status_message);
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return $ret;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);
sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
?>