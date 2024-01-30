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
        $final_res = json_decode($SMS_RETURN_BUF , true); //Convert into json, the $res could be one SimpleXMLElement Object
        //debug_dump($final_res , "rest_generic_apply_conf.php DEVICE RESPONSE, SMS_RETURN_BUF=$SMS_RETURN_BUF;  final_res=\n");
        //{"response":{"status":"succ","result":{"data":{"row":{"id":"154","name":"test_16h56"}}}}}
        //{"response":{"status":"fail","err":{"msg":"Import Fail","code":"20007","description":{"error_data":{"row":{"name":"test_17h05_bad","message":{"row":" is not a valid IP address."}}}}}}}
        if (isset($final_res["response"]) && isset($final_res["response"]["status"]) && $final_res["response"]["status"] == "fail")
        {
           if (isset($final_res["response"]["err"])) {
             $SMS_OUTPUT_BUF = json_encode($final_res["response"]["err"]);
           } 
           return ERR_SD_CMDFAILED;
        }
      }
      $line = get_one_line($configuration);
    }  
    return SMS_OK;

}


?>
