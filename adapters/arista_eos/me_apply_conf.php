<?php

require_once 'smsd/sms_common.php';
require_once load_once('arista_eos', 'me_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function me_apply_conf($configuration)
{
    global $sdid;
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $apply_errors;
    global $operation;
    global $SD;
    global $SMS_RETURN_BUF;
    
    // Save the configuration applied on the router
    save_result_file($configuration, 'conf.applied');
    $SMS_OUTPUT_BUF = '';
        
    $line = get_one_line($configuration);
    while ($line !== false)
    {
    	$line = trim($line);
    	
    	if (!empty($line))
    	{
    		$res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, '');
    		
    		$SMS_RETURN_BUF = json_encode($res);
    	}
    	$line = get_one_line($configuration);
    }
    if ($sms_sd_ctx->no_save_config_to_startup != 'true') {
        $ret = copy_config_to_startup();
    }
    
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    return SMS_OK;
}

function copy_config_to_startup()
{
    global $sms_sd_ctx;

    $http_header_str = "content-type:application/json-rpc";
    $sms_sd_ctx->http_header_list = explode("|", $http_header_str);
    $payload = '{
        "jsonrpc": "2.0",
        "method": "cli",
        "params": {
        "cmd": "copy running-config startup-config",
        "version": 1
        },
        "id": 1
    }';
    $cmd = "POST#/ins#$payload";
    $ret = $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
    return $ret;
}

?>
