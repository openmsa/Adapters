<?php
/*
 * Available global variables
 * $revision1 fisrt revision to get
 * $revision2 second revision to get
 * $addon addon card (optionnal)
 * $sms_sd_info sd_info structure
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_csp pointer to csp context to send response to user
 * $sdid
 * $sms_module module name (for patterns)
 */

// Generate template from change management
require_once 'smsd/gen_template.php';

require_once "$db_objects";

function get_level($arr) {
    $level = 0;
    while ($arr[$level + 1] === ' ') {
        $level ++;
    }
    return $level;
}

function gen_template_callback($rev1_file, $rev2_file) {
    global $sdid;
    global $sms_csp;
    global $revision1;
    global $revision2;

    $diff_cmd = "diff -U 1000 $rev1_file $rev2_file | sed -e '/^ !/d' -e '/Current configuration :/d' | tail -n +5";

    $tpl = "! Template generated from the differences between $revision1 and $revision2 for device $sdid\n";

    $buffer = shell_exec("sh -c \"$diff_cmd\"");
    $context = array();
    $line = get_one_line($buffer);
    $context_need_display = array();
    while ($line !== false) {
        if ($line === '------------------------------') {
            break;
        }
        $l = str_split($line);
        $level = get_level($l);
        $the_line = trim(substr($line, 1));

        switch ($l[0]) {
            case '+':
                if ($l[1] !== '!') {
                    for ($i = 0; $i < $level; $i ++) {
                        if ($context_need_display[$i]) {
                            $tpl .= "!\n{$context[$i]}\n";
                            $context_need_display[$i] = false;
                        }
                    }
                    $tpl .= str_repeat(' ', $level);
                    $tpl .= "{$the_line}\n";
                }
                $context_need_display[$level] = false;
                break;

            case '-':
                if ($l[1] !== '!') {
                    for ($i = 0; $i < $level; $i ++) {
                        if ($context_need_display[$i]) {
                            $tpl .= "!\n{$context[$i]}\n";
                            $context_need_display[$i] = false;
                        }
                    }
                    $tpl .= str_repeat(' ', $level);
                    $tpl .= "no {$the_line}\n";
                }
                break;

            case ' ':
                $context[$level] = $the_line;
                $context_need_display[$level] = true;
                $context_need_display[$level + 1] = false;
                break;
        }
        $line = get_one_line($buffer);
    }

    sms_send_user_ok($sms_csp, $sdid, $tpl);

    debug_dump($tpl, "TEMPLATE");

    return SMS_OK;
}

$ret = gen_template($revision1, $revision2);

return $ret;

?>

