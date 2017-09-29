<?php
/*
 * Version: $Id: do_get_report.php 22221 2009-09-30 12:46:20Z tmt $
 * Created: Jul 31, 2008
 * Available global variables
 * 	$sms_sd_ctx		pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info	sd_info structure
 *  $sdid
 *  $sms_module		module name (for patterns)
 *  $fullReport		boolean false => VPN only
 */

// Get VPN report of the device

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'nsrpc.php');

$report_stages = array(
    0 => array('cmd' => 'MONITOR GETSA', 'descr' => 'Ipsec SAD', 'expect' => 'SRPClient>', 'encoded' => false),
    1 => array('cmd' => 'MONITOR GETSPD', 'descr' => 'Ipsec SPD', 'expect' => 'SRPClient>', 'encoded' => false),
);

$SMS_RETURN_BUF = '';

function format_output(&$output, $descr)
{
  // see WebContent/style/style.css in SES + custo
  $SMS_RETURN_BUF .= <<<EOF
<span class="reportDescr">$descr</span>
<div class="reportOutput">
<pre>$output</pre>
</div>

EOF;
}


try
{
  netasq_connect();

  $result_string = '';
  foreach ($report_stages as $report_stage)
  {
    $cmd = $report_stage['cmd'];
    if ($report_stage['encoded'])
    {
      $result_string = $conf->send_expect_b64($cmd, $report_stage['expect']);
    }
    else
    {
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, $report_stage['expect']);

      if (empty($buffer))
      {
        continue;
      }

      if (is_error($buffer, $cmd) === true)
      {
        sms_log_error(__FILE__.':' . __LINE__ . ": Command [$cmd] has failed\n$buffer\n");
        continue;
      }

      $line = get_one_line($buffer);
      while ($line !== false)
      {
        if (strpos($line, 'code=') === false && strpos($line, 'SRPClient>') === false && strpos($line, $cmd) === false)
        {
          $line = trim($line);
          $result_string .= "$line\n";
        }
        $line = get_one_line($buffer);
      }
    }

    format_output($result_string, $report_stage['descr']);
  }

  netasq_disconnect();
}
catch (Exception $e)
{
  netasq_disconnect();
  return $e->getCode();
}

return SMS_OK;

?>