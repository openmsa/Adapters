<?php
/*
 * Version: $Id: do_get_activity_report.php 24201 2009-11-25 10:17:13Z tmt $
 * Created: Jui 25, 2008
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 */

// Get activity report of the device

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'netasq_configuration.php');


$report_stages = array(
0 => array("cmd" => "system information", "descr" => "system information", "expect" => "SRPClient>", "encoded" => true),
);

try
{
  netasq_connect();

  $conf = new netasq_configuration($sdid);

  $stage_count = count($report_stages);
  $stage = 0;
  $result_string = '';
  while ($stage < $stage_count)
  {
    $report_stage = $report_stages[$stage];
    $stage_msg = sms_user_message_add("", SMS_UMN_STAGE, $report_stage['descr']);
    if ($report_stage["encoded"])
    {
      $ret = $conf->send_expect_b64($report_stage["cmd"], $report_stage["expect"]);
    }
    else
    {
      $ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $report_stage["cmd"], $report_stage["expect"]);
      if (!empty($ret))
      {
        // trimming first and last lines
        $pos = strpos($ret, "\n");
        if ($pos !== false)
        {
          $ret = substr($ret, $pos);
        }
        $pos = strrpos($ret, "\n");
        if ($pos !== false)
        {
          $ret = substr($ret, 0, $pos + 1);
        }
      }
    }
    $stage_msg = sms_user_message_add($stage_msg, SMS_UMN_RESULT, htmlentities($ret));

    $result_string = sms_user_message_array_add($result_string, $stage_msg);

    $stage += 1;
  }

  netasq_disconnect();
}
catch(Exception $e)
{
  netasq_disconnect();
  sms_send_user_error($sms_csp, $sdid, "", $e->getCode());
  return SMS_OK;
}

$user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
$user_message = sms_user_message_add_array($user_message, SMS_UMN_RESULT, $result_string);
sms_send_user_message($sms_csp, $sdid, $user_message);

return SMS_OK;
?>