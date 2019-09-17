<?php
/*
 * Date : Feb 10, 2017
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Connection to the device

require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_connect.php');

try
{
  global $sms_sd_ctx;

  netasq_connect();

  netasq_disconnect();
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  return $e->getCode();
}

return SMS_OK;

?>