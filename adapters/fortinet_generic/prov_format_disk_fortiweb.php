<?php
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'common.php');
require_once "$db_objects";

// -------------------------------------------------------------------------------------
// FORMAT DISK
// -------------------------------------------------------------------------------------
function prov_format_disk_fortiweb($sms_csp, $sdid, $sms_sd_info, $stage)
{
	global $ipaddr;
	global $login;
	global $passwd;
	global $port;
	global $sms_sd_ctx;

	$tab[0] = "#";
	$tab[1] = "y/n)";

	
	//msk@ubiqube.com 2018-02-21
	//loop retry of disk format 3 times with a 60second sleep time
    for($i = 0; $i <3;$i++)
    {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "execute formatlogdisk", $tab);
        if ($index === 1)
    	{
    		unset($tab);
    		$tab[0] = "#";
    		$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab);
    		if ($index === -1 && $i == 2)
    		{
    			throw new Exception("Connection Failed", ERR_LOCAL_CMD);
    		}    		
    		else if ($index === 0)
    		{
    			fortinet_generic_disconnect();
    			break;
    		}
    		else
    		{
    		    sleep(60);
    		}
    	}
    	else
    	{
    	    sleep(60);
    	}
    }

    //failed to connect to the device
	if ($index === -1)
	{	   
		throw new Exception("Connection Failed", ERR_LOCAL_CMD);
	}

	$ret = wait_for_device_up($ipaddr);
	if ($ret == SMS_OK)
	{
		fortinet_generic_connect($ipaddr, $login, $passwd, $port);
	}
	else
	{
		throw new SmsException("Connection Failed", $ret);
	}

	// Wait disk status
	$wait = 20;
	$loop = 300 / $wait;
	while ($loop > 0)
	{
		$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'get system status', '#');
		if (strpos($buffer, 'Log hard disk: Available') !== false)
		{
			return SMS_OK;
		}
		$loop--;
		sleep($wait);
	}
	throw new SmsException("Format Disk Failed", ERR_SD_CMDFAILED);
}


?>
