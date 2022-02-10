<?php

require_once 'smsd/sms_common.php';
require_once "$db_objects";

/**
 * Apply the configuration
 * @param string  $configuration	configuration to apply
 */
function me_apply_conf($configuration)
{
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;
    global $SMS_OUTPUT_BUF;

    // Save the configuration applied on the router
    save_result_file($configuration, 'conf.applied');
    $SMS_OUTPUT_BUF = '';

    try
    {
      // Apply configuration
      $line = get_one_line($configuration);
      while ($line !== false)
      {
      	$line = trim($line);

      	if (!empty($line))
      	{
      	    $res = $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $line, '');
      		debug_dump($res, "APPLY CONF");
            $SMS_RETURN_BUF = json_encode($res);
      	}
      	$line = get_one_line($configuration);
      }

      // Deploy job
      $deploy_path = '/api/fdm/latest/operational/deploy';
      $cmd = "POST#{$deploy_path}";
      $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
      $state = $sms_sd_ctx->get_array_response()['state'];
      $id = $sms_sd_ctx->get_array_response()['id'];

      // Check deploy job status
      $cmd = "GET#{$deploy_path}/{$id}";
      $waiting_time = 5; // seconds
      $timeout = 120; // seconds
      do
      {
        sleep($waiting_time);
        $timeout -= $waiting_time;
        $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
        $state = $sms_sd_ctx->get_array_response()['state'];
      }
      while ($state == 'DEPLOYING' && $timeout > 0);

      if ($state == 'DEPLOYING')
      {
        $msg = "__FILE__ . ':' . __LINE__: Deployement timeout";
        throw new SmsException($msg, ERR_SD_CMDTMOUT, __FILE__ . ':' . __LINE__);
      }
    }
    catch (SmsException $e)
    {
      $SMS_OUTPUT_BUF = $e->getMessage();
      return $e->getCode();
    }

    return SMS_OK;
}

?>