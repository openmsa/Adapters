<?php
/*
 * Version: $Id$
* Available global variables
*/

// Enter Script description here


require_once 'smsd/sms_common.php';
require_once load_once('opendaylight', 'opendaylight_connection.php');

require_once "$db_objects";

	$connect = new opendaylightConnection();
	$url = $connect->get_availibility();
	$delay = 50;
	$test_availibility = shell_exec("curl --connect-timeout {$delay} --max-time {$delay} -X DELETE " . $url) ;
	if(strlen($test_availibility)>200)
	{
		return SMS_OK;
	}
	sms_log_error("connection error : $url");
  	return $test_availibility;
?>