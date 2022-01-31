<?php

// Device adaptor

require_once 'smsd/sms_common.php';


/**
 * Does nothing
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false)
{
  global $SMS_RETURN_BUF;
  $SMS_RETURN_BUF = '{}';
  return SMS_OK;
}

?>