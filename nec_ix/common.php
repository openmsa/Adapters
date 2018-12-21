<?php

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('nec_ix', 'nec_ix_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported"
);


/**
 * Function reboot
 */
function func_reboot() {
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $result;

    unset($tab);
    $tab[0] = 'Save? [yes/no]';
    $tab[1] = 'reload the router? (Yes or [No])';
    $tab[2] = 'reboot.';

    $cmd_line = "reload";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);

//    if ($index === 0) {
//        $cmd_line = "no";
//        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
//    }
    if ($index === 1) {
        $cmd_line = "yes";
//        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
        $sms_sd_ctx->send(__FILE__.':'.__LINE__,  "{$cmd_line}\r");
    }
}

// extract the prompt
function extract_prompt()
{
  global $sms_sd_ctx;

  /* for synchronization */
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'conf', '(config)#');
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'exit', '#');
  $buffer = trim($buffer);
  $buffer = substr(strrchr($buffer, "\n"), 1);  // get the last line
  $sms_sd_ctx->setPrompt($buffer);
}

?>
