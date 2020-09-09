<?php
/*
 * Version: $Id$
* Created: Jun 26, 2015
* Available global variables
*  $sms_csp           Pointer to csp context to send response to user
*  $sdid              Device ID for which the log files have to be listed
*  $optional_params   Contains start and end dates
*/

require_once 'smsd/sms_common.php';
require_once "$db_objects";

$network = get_network_profile();
$SD = &$network->SD;

$sd_ip_config = $SD->SD_IP_CONFIG;

$sd_login_entry = $SD->SD_LOGIN_ENTRY;

$sd_passwd_entry = $SD->SD_PASSWD_ENTRY;

$params = preg_split("#\r\n#", $optional_params);

$msg_id = trim($params[0]);

if(!empty($msg_id))
{
	//get authenicated to the device and save the cookies
	$login_url = "https://$sd_ip_config/logincheck";
	$login_post = "username=$sd_login_entry&secretkey=$sd_passwd_entry";

	$cookie_file = "/tmp/cookies_".$sd_ip_config.".txt";

	$login_success = true;
	$filemodtime = 0;
	if (file_exists($cookie_file)) {
		$filemodtime = filemtime($cookie_file);
	}

	if (time() - $filemodtime > (1*3600)) {
		// delete the cookie file if saved before 1 hour
		unlink($cookie_file);
	}

	if(!file_exists($cookie_file)){
		$login_success = false;
		$login_cmd = "wget -d --save-cookies=$cookie_file --keep-session-cook --no-check-certificate --post-data='$login_post' $login_url 2>&1";
		echo $login_cmd."\n";
		$ret = exec_local(__FILE__ . ':' . __LINE__, $login_cmd, $output_array);
		foreach ($output_array as $output)
		{
			if (preg_match('/Done\ssaving\scookies/', $output, $match))
			{
				$login_success = true;
				break;
			}
		}
	}

	if(!$login_success)
	{
		return ERR_SD_ADM_AUTH;
	}

	if($login_success)
	{
		//if cookies saved successfully - get the detail log
		$get_log_url = "https://$sd_ip_config/log_aggregate/getLogByID";
		$get_log_post = "msgID=$msg_id&logType=3&is_aggregate=1&logfile=alog.log";
		//$output_file = "/opt/sms/routerlogs/$sdid/getLogByID_" . rand(100000, 999999).".json";
		$output_file = "/opt/sms/routerlogs/$sdid_getLogByID";
		
		$get_log_cmd = "wget -d --load-cookies=$cookie_file --no-check-certificate --post-data='$get_log_post' $get_log_url 2>&1 --output-document $output_file";
		unset($output_array);
		$ret = exec_local(__FILE__ . ':' . __LINE__, $get_log_cmd, $output_array);
		//var_dump($output_array);
		foreach ($output_array as $output)
		{
			if (preg_match('/Saving\sto:/', $output, $match))
			{
				$detail_json = file_get_contents($output_file);
				//echo "RESULT JSON ============================>".$detail_log."\n";
				unlink($output_file);
				break;
			}
		}
		if(!$detail_json || $detail_json == "nulldata")
		{
			return ERR_LOCAL_CMD;
		}
	    if(!preg_match('/msg_id/', $detail_json, $match))
        {
        	sms_send_user_error($sms_csp, $sdid, "msg_id not found", ERR_VERB_BAD_PARAM);
            sms_close_user_socket($sms_csp);
            return SMS_OK;
            }
		}
}

if ($ret !== 0)
{
	sms_send_user_error($sms_csp, $sdid, "", $ret);
}else{
	sms_send_user_ok($sms_csp, $sdid,$detail_json);
//	sms_send_user_ok($sms_csp, $sdid, $output_file);
}
sms_close_user_socket($sms_csp);
return SMS_OK;



?>
