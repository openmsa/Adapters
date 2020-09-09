<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function citrix_netscalar_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
    global $sms_sd_ctx;
    
    $sms_sd_ctx = new SshConnection($sd_ip_addr, $login, $passwd, $port_to_use);   
    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function citrix_netscalar_disconnect()
{
    global $sms_sd_ctx;
    try
    {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
		$sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
    }
    catch (Exception | Error $e)
    {
        // ignore errors
    }
    
    $sms_sd_ctx = null;
    return SMS_OK;
}

?>