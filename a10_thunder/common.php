<?php

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

require_once load_once('a10_thunder', 'a10_thunder_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported"
);

// -----------------------------------------------------
// NETWORK FUNCTIONS
// -----------------------------------------------------

// Convert network/mask notation for extended ACLs
// 10.11.1.26/255.255.255.255 => host 10.11.1.26
// 0.0.0.0/0.0.0.0 => any
// else keep the net/mask notation
function convert_network_ext($network, $mask) {
    if (empty($network)) {
        return "any";
    }

    if (empty($mask)) {
        $mask = "255.255.255.255";
    }

    $network = get_network($network, $mask);

    switch ($mask) {
        case '255.255.255.255':
            $result = "host $network";
            break;

        case '0.0.0.0':
            $result = "any";
            break;

        default:
            $inverted_mask = invert_mask($mask);
            $result = "$network $inverted_mask";
            break;
    }

    return $result;
}

// Convert network/mask notation for standard ACLs
// 10.11.1.26/255.255.255.255 => 10.11.1.26
// 0.0.0.0/0.0.0.0 => any
// else keep the net/mask notation
function convert_network_std($network, $mask) {
    if (empty($network)) {
        return "any";
    }

    if (empty($mask)) {
        $mask = "255.255.255.255";
    }

    $network = get_network($network, $mask);

    switch ($mask) {
        case '255.255.255.255':
            $result = "$network";
            break;

        case '0.0.0.0':
            $result = "any";
            break;

        default:
            $inverted_mask = invert_mask($mask);
            $result = "$network $inverted_mask";
            break;
    }

    return $result;
}

/**
 * Function reboot
 */
function func_reboot() {
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $result;

    unset($tab);
    $tab[0] = 'Save? [yes/no]';
    $tab[1] = 'reboot? [yes/no]';
    $tab[2] = 'reboot.';

    $cmd_line = "reboot";
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);

    if ($index === 0) {
        $cmd_line = "no";
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
    }
    if ($index === 1) {
        $cmd_line = "yes";
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
    }
}
?>
