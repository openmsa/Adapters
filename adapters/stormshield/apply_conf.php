<?php

require_once load_once('stormshield', 'connect.php');

/**
 * Apply the configuration
 * @param string  $configuration	configuration to apply
 */
function apply_conf($configuration)
{
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;
    global $SMS_OUTPUT_BUF;

    // Save the configuration applied on the router
    save_result_file($configuration, 'conf.applied');
    $SMS_OUTPUT_BUF = '';

    if (empty($configuration))
    {
      $SMS_RETURN_BUF = '{}';
    }
    else
    {
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $configuration, '');
      $SMS_RETURN_BUF = json_encode($res);
    }

    return SMS_OK;
}
