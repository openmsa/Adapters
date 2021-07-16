<?php
/*
 * Version: $Id$
 * Created: Jul 09, 2021 by LED
 * Description: It will get files from the given device and copy the file into the MSA into /opt/fmc_repository/Datafiles directorie
 *
 * Available global variables
 * $src_dir  : source directorie on the device
 * $file_pattern  : file_name (it could be "test*" to copy all files test1, test2, testsxxx at the same times 
 * $dst_dir  : destination directorie on the MSA after /opt/fmc_repository/Datafiles

 * Example to get the file /tmp/test.txt from the device_id 133  and copy the file into the MSA into /opt/fmc_repository/Datafiles/test2
 * curl -v --insecure  --header 'Accept: application/json' --header 'Authorization: Bearer eyJhbGciOiJIUzU..................' -X POST 'https://127.0.0.1/ubi-api-rest/sms/cmd/get_files/133/?params=src_dir=/tmp/,file_pattern=test.txt,dest_dir=test2'

 */

// Verb JSACMD SDID GETDATAFILES source_dir file_pattern destination

require_once 'smsd/sms_common.php';

require_once load_once('linux_generic', 'linux_generic_connect.php');
require_once load_once('linux_generic', 'linux_generic_configuration.php');
require_once "$db_objects";

try {
    $status_type = 'GETDATAFILES';

    $params = preg_match("/src_dir=(.*),\s*file_pattern=(.*),\s*dest_dir=(.*)/",$optional_params,$match);
    $src_dir      = $match[1];
    $file_pattern = $match[2];
    $dst_dir      = trim($match[3]);

    if (empty($src_dir) || empty($file_pattern) || empty($dst_dir)) {
      sms_send_user_error($sms_csp, $sdid, "", ERR_VERB_BAD_PARAM);
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
    $ret = $conf->get_data_files($status_type, $src_dir, $file_pattern, $dst_dir);

    linux_generic_disconnect(true);

} catch (Exception | Error $e) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $status_message . $e->getMessage());
    sms_sd_unlock($sms_csp, $sms_sd_info);
    linux_generic_disconnect();
    return SMS_OK;
}

if ($ret !== SMS_OK) {
    sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', $status_message);
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);
sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
?>