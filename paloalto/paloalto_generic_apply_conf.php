<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('paloalto_generic', 'paloalto_generic_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration        configuration to apply
 * @param boolean $copy_to_startup      copy in startup-config+reboot instead of running-config+write mem
 */
function paloalto_generic_apply_conf($configuration)
{
    global $sdid;
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $apply_errors;
    global $operation;
    global $SD;

    // Save the configuration applied on the router
    save_result_file($configuration, 'conf.applied');
    $SMS_OUTPUT_BUF = '';
    $api_return_value = "";

    $line = get_one_line($configuration);
    while ($line !== false) {
        $line = trim($line);
        if (!empty($line)) {
            $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, '/response');
            $msg = res_get_msg($res);
            if (trim($res['status']) !== 'success') {
                $line = urldecode($line);
                $SMS_OUTPUT_BUF .= "{$line}\n\n{$msg}\n";
            }
            else {
                $api_return_value = $msg;
            }

            if (!empty($res->result) && !empty($res->result->job)) {
                $job = $res->result->job;

                do {
                    sleep(2);
                    $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=op&cmd='.urlencode("<show><jobs><id>{$job}</id></jobs></show>"));
                    if (!empty($operation) && $result->result->job->status == 'ACT') {
                        status_progress("progress {$result->result->job->progress}%", $operation);
                    }
                } while ($result->result->job->status != 'FIN');
                if (!empty($SMS_OUTPUT_BUF) || $result->result->job->result == 'FAIL') {
                    $SMS_OUTPUT_BUF .= $result->result->job->asXml();
                }
            }
        }
        $line = get_one_line($configuration);
    }

    save_result_file($SMS_OUTPUT_BUF, "conf.error");
    if (!empty($SMS_OUTPUT_BUF)) {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
        return ERR_SD_CMDFAILED;
    }

    // set API return value (json.message) for success cases
    $SMS_RETURN_BUF = $api_return_value;

    return SMS_OK;
}

function res_get_msg($res)
{
  $msg = "";
  if (!empty($res->msg->line->line)) {
      $msg = (String)$res->msg->line->line;
  } elseif (!empty($res->msg->line)) {
      $msg = (String)$res->msg->line;
  } elseif (!empty($res->msg)) {
      $msg = (String)$res->msg;
  } elseif (!empty($res->result->msg)) {
      $msg = (String)$res->result->msg;
  }
  return $msg;
}

function send_configuration_file($configuration)
{
    global $sdid;
    global $sms_sd_ctx;

    save_result_file($configuration, 'conf.applied');
    save_result_file($configuration, 'conf.xml');

    $filepath = "{$_SERVER['GENERATED_CONF_BASE']}/$sdid/conf.xml";
    return $sms_sd_ctx->send_file(__FILE__ . ':' . __LINE__, $filepath);
}


?>
