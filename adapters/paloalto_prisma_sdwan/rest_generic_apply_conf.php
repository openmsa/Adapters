<?php

require_once 'smsd/sms_common.php';
require_once load_once('rest_generic', 'rest_generic_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function rest_generic_apply_conf($configuration)
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
    
    return SMS_OK;
}


?>