<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('versa_appliance', 'versa_appliance_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function versa_appliance_apply_conf($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $operation;
  global $SD;
  
  
	echo ("::::::::::::::::::::::::::::::::::   APPLY_CONF ::::::::::::::::::::::::::::::::::\n");
 if (strlen($configuration) === 0)
  {
    return SMS_OK;
  }
  
  debug_dump($configuration, 'CONFIG TO APPLY');
  
  $edddit=$sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "configure", ")%");
  echo "Enable mode configuration ".$edddit."\n";
  
  
  // Save the configuration applied on the router
  //save_result_file($configuration, 'conf.applied');
  
  $SMS_OUTPUT_BUF = '';
  
  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    if (!empty($line))
    {
      $SMS_OUTPUT_BUF .= $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $line, ")%");
    }
    $line = get_one_line($configuration);
  }
  
  echo "RESUTL:".$SMS_OUTPUT_BUF."\n";
  //save_result_file($SMS_OUTPUT_BUF, "conf.error");
   $edddit=$sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "exit");
  echo "Exit mode configuration ".$edddit."\n";
  
  echo (":::::::::::::::::::::::::::::::: END APPLY_CONF ::::::::::::::::::::::::::::::::\n");
  return SMS_OK;
}

?>