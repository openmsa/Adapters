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
 */

// Verb JSACMD SDID GET_HA_STATUS serial_number

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'fortinet_generic_connect.php');
require_once load_once('fortinet_generic', 'fortinet_generic_configuration.php');
require_once "$db_objects";

$network = get_network_profile();
$SD = &$network->SD;
$result_array = array();
$result = '';
$result_ha = '';
$sd_model = $SD->MOD_ID;

//dont get ha status on fortiweb model
if($sd_model == 1130)
{
	sms_send_user_ok($sms_csp, $sdid, $result);
	return SMS_OK;
}

$params = preg_split("#\r\n#", $optional_params);

$serial_number = trim($params[0]);

//hardcoded for test
/*if($serial_number == "FGVM040000034770"){
 $result = '{"ha_status":"Master","ha_priority":"200","hostname":"Seto_UTM_HA01","serial_number":"FGVM040000050499","port8":"MASTER","port9":"MASTER","port10":"MASTER"}';
}
if($serial_number == "FGVM040000030030"){
$result = '{"ha_status":"Slave","ha_priority":"100","hostname":"Seto_UTM_HA02","serial_number":"FGVM040000045721","port8":"SLAVE","port9":"SLAVE","port10":"SLAVE"}';
}

sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;*/

$ret = fortinet_generic_connect();
if ($ret !== SMS_OK)
{
	sms_log_error(__FILE__.':'.__LINE__.": fortinet_generic_connect() failed\n");
	sms_send_user_error($sms_csp, $sdid, "", $ret);
	return $ret;
}

$on_error_fct = 'fortinet_generic_disconnect';


$cmd = "get sys ha status | grep $serial_number";

// send command
$result .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'lire dans sdctx', 36000000, false);

/*Seto_UTM_HA01 # get system ha status | grep FGVM040000050499
Master:200 Seto_UTM_HA01    FGVM040000050499 0
Master:0 FGVM040000050499
*/

$cmd_ha = "get router info vrrp";

$result_ha .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_ha, 'lire dans sdctx', 36000000, false);

/*
 * Seto_UTM_HA01 # get router info vrrp
Interface: port8, primary IP address: 100.127.253.108
  UseVMAC: 1, SoftSW: 0, BrPortIdx: 0, PromiscCount: 1
  HA mode: master (0:3)
  VRID: 2
    vrip: 100.127.253.110, priority: 255, state: MASTER
    adv_interval: 1, preempt: 1, start_time: 3
    vrmac: 00:00:5e:00:01:02
    vrdst:
    vrgrp: 1

Interface: port9, primary IP address: 192.168.1.252
  UseVMAC: 1, SoftSW: 0, BrPortIdx: 0, PromiscCount: 1
  HA mode: master (0:3)
  VRID: 3
    vrip: 192.168.1.254, priority: 255, state: MASTER
    adv_interval: 1, preempt: 1, start_time: 3
    vrmac: 00:00:5e:00:01:03
    vrdst:
    vrgrp: 1

Interface: port10, primary IP address: 192.168.2.252
  UseVMAC: 1, SoftSW: 0, BrPortIdx: 0, PromiscCount: 1
  HA mode: master (0:3)
  VRID: 4
    vrip: 192.168.2.254, priority: 255, state: MASTER
    adv_interval: 1, preempt: 1, start_time: 3
    vrmac: 00:00:5e:00:01:04
    vrdst:
    vrgrp: 1


Seto_UTM_HA01 #
 */

unset($on_error_fct);
fortinet_generic_disconnect();

if(preg_match("/(?<ha_status>Master|Slave)\s?:(?<ha_priority>\\d+)\s(?<hostname>\S+)\s+(?<serial_number>\S+)/", $result, $match) > 0 )
{
	$result_array["ha_status"] = $match["ha_status"];
	$result_array["ha_priority"] = $match["ha_priority"];
	$result_array["hostname"] = $match["hostname"];
	$result_array["serial_number"] = $match["serial_number"];
}
else{
	$result = "Standalone";
}
$i = 0;
preg_match_all("/Interface:\s(?<port_number>\S+),(.*\n){4}.*?state:\s(?<port_state>MASTER|BACKUP)/m", $result_ha, $match_ha);
if(count($match_ha) > 0)
{
	foreach($match_ha["port_number"] as $match_port)
	{
		$port_state = $match_ha["port_state"][$i++];
		
		 if($port_state == "BACKUP")
		 {
		 	$port_state = "SLAVE";
		 }
		
		$result_array[$match_port] = $port_state;
	}
}

$result =  json_encode($result_array, JSON_FORCE_OBJECT);

sms_send_user_ok($sms_csp, $sdid, $result);

return SMS_OK;



?>