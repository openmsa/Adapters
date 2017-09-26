<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('juniper_srx', 'juniper_srx_connect.php');
require_once load_once('juniper_srx', 'apply_errors.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function juniper_srx_apply_command_delete($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $SMS_OUTPUT_BUF;
  global $SD;
  
  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "edit");
  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $configuration);
  
  $sendexpect_result = '';
  $SMS_OUTPUT_BUF = '';
  
  $tab[0] = 'root#';
  
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
  $SMS_OUTPUT_BUF .= $sendexpect_result;
  
  switch ($index)
  {
    case 0:
      //commit complete
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "commit");
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
      unset($tab);
      
      $tab[0] = $sms_sd_ctx->getPrompt();
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      
      $SMS_OUTPUT_BUF .= $sendexpect_result;
      
      if ($index !== 0)
      {
        return ERR_SD_CMDFAILED;
      }
      break;
  }
  
  save_result_file($SMS_OUTPUT_BUF, "conf.error");
  return SMS_OK;
}

?>
