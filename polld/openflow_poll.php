<?php
require_once 'smsd/sms_common.php';
require_once "$db_objects";
try
{

	//$ret = SMS_OK;

	$net_conf = get_network_profile();
	$sd = $net_conf->SD;

	$dpid = $sd->SD_DPID;
	$delay = 50;
	$result=exec("curl --connect-timeout {$delay} --max-time {$delay} http://127.0.0.1:63080/wm/core/controller/switches/json",$array_result,$code);

	if($code != 0){
		//$ret = "Connection problem to OpenFlow Controller";
		$ret = ERR_SD_NETWORK;
	}else{
		$retourstrpos=strpos($result,$dpid);
		if($retourstrpos === false){
			//$ret = "Device not connected to OpenFlow Controller";
			$ret = ERR_SD_NETWORK;
		}
		else
		{
			$ret = SMS_OK;
		}
	}



	if($ret === SMS_OK)
	{
		return SMS_OK;
	}
	else
	{
		throw new Exception("Connection failed to the device",$ret);
	}
}
catch (Exception | Error $e)
{
	$msg = $e->getMessage();
	$code = $e->getCode();
	sms_log_error("connection error : $msg ($code)");
	return $code;
}




?>
