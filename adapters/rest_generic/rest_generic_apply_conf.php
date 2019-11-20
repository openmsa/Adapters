<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('rest_generic', 'c_connect.php');
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
    
    echo (":::::::::::::::::::::::::::: APPLY2 ::::::::::::::::::::::::::::\n");
    
    $line = get_one_line($configuration);
    $res = "";
    while ($line !== false)
    {
        $line = trim($line);
        
        if (!empty($line))
        {
            $res .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, '//root');                                
           
        }
        $line = get_one_line($configuration);
    }
    $SMS_RETURN_BUF = json_encode($res);
    $publish_cmd = "publish' -d '{}";
    $publish = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $publish_cmd, '//root');  
    return SMS_OK;
}

?>