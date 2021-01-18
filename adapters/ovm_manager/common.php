<?php
/*
 * Date : Oct 19, 2007
*/

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('ovm_manager', 'ovm_manager_connect.php');

function func_reboot($msg = '')
{
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $result;

	//Are you sure you wish to restart? (yes/cancel)
	//[cancel]: yes
	$tab[0] = '[cancel]:';
	$tab[1] = 'Restarting now...';
	$tab[2] = $sms_sd_ctx->getPrompt();

	$cmd_line = "restart $msg";

    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_line, $tab);

    if ($index === 0)
    {
      $cmd_line = "yes";
      sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd_line, $tab[1]);
  	}
}
?>