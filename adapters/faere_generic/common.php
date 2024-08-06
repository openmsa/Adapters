<?php
/*
 * Date : Oct 19, 2007
*/

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('faere_generic', 'faere_generic_connect.php');

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

function send_file($source, $destination, $ip, $login, $password){
 
        $SD = &$network->SD;
        $sd_mgt_port = $SD->SD_MANAGEMENT_PORT;    

	$ret_scp = exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/sms_scp_transfer -s $source -d $destination -l $login -a $ip -p '$password' -P $sd_mgt_port", $output);
	if ($ret_scp !== SMS_OK){
			return $ret_scp;
	}
	return SMS_OK;
}
?>
