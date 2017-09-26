<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/sms_common.php';

// etats de connexion
define('NOT_CONNECTED', 0);
define('LOCAL_CTX', 1);
define('LOCAL_TELNET', 2);
define('REMOTE_CLI', 3);

// version inférieure a 8, nouvelle numerotation
define('VERSION_8_0_0', 800);
// version de mise a jour, comportement different si < ou >=
define('VERSION_8_1_4', 814);
// version de generation de conf, comportement different si < ou >=
define('VERSION_9_0_0', 900);
// version de generation de conf, comportement different si < ou >=
define('VERSION_9_0_2', 902);

define('DEFAULT_LOGIN', 'admin');
define('DEFAULT_PASSWORD', 'admin');

define ('DEFAULT_MODID', 82);

function set_log($log_level, $log_ref, $log_msg)
{
  global $sms_sd_info;

  $log['log_level'] = $log_level;
  $log['log_reference'] = "VNOC-{$log_level}-{$log_ref}";
  $log['log_msg'] = "%{$log['log_reference']}: {$log_msg}";
  sms_bd_set_log($sms_sd_info, $log);
}

?>