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

    }
    catch (SmsException $e)
    {
      $SMS_OUTPUT_BUF = $e->getMessage();
      return $e->getCode();
    }

    return SMS_OK;
}

?>