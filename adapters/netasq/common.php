<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/sms_common.php';

function set_log($log_level, $log_ref, $log_msg)
{
  global $sms_sd_info;

  $log['log_level'] = $log_level;
  $log['log_reference'] = "VNOC-{$log_level}-{$log_ref}";
  $log['log_msg'] = "%{$log['log_reference']}: {$log_msg}";
  sms_bd_set_log($sms_sd_info, $log);
}

?>