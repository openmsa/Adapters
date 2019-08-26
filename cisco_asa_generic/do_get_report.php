<?php
/*
 * Version: $Id: do_get_report.php 22221 2009-09-30 12:46:20Z tmt $
 * Created: Jul 31, 2008
 * Available global variables
 *      $sms_sd_ctx             pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $fullReport         boolean false => VPN only
 */


// Enter Script description here

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('cisco_asa_generic', 'device_connect.php');

if ($fullReport)
{
 $report_stages = array(
 0 => array("cmd" => "show version", "descr" => "Version and Licenses"),
 1 => array("cmd" => "show version", "descr" => "Logs"),
 2 => array("cmd" => "show traffic", "descr" => "Traffic"),
 3 => array("cmd" => "show xlate", "descr" => "Current translation information"),
 4 => array("cmd" => "show crypto isakmp stats", "descr" => "Current IKE Phase 1 SAs"),
 5 => array("cmd" => "show crypto isakmp sa", "descr" => "Current IKE Phase 1 SAs"),
 6 => array("cmd" => "show crypto ipsec sa", "descr" => "Current IPSEC Tunnels (IKE Phase 2 SAs)"),
 7 => array("cmd" => "dir flash:", "descr" => "Flash files")
 );
}
else
{
 $report_stages = array(
 1 => array("cmd" => "show version", "descr" => "Logs"),
 5 => array("cmd" => "show crypto isakmp sa", "descr" => "Current IKE Phase 1 SAs"),
 6 => array("cmd" => "show crypto ipsec sa", "descr" => "Current IPSEC Tunnels (IKE Phase 2 SAs)"),
 7 => array("cmd" => "dir flash:", "descr" => "Flash files")
 );

}

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
        device_connect();
        $result_string = '';
        foreach ($report_stages as $report_stage)
        {
                $cmd = $report_stage['cmd'];

                $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, "#");

                if (empty($buffer))
                {
                        continue;
                }

                if(preg_match("/Invalid input/", $buffer) == 1)
                {
                        $buffer = "";
                }

                $first = true;
                $line = get_one_line($buffer);
                while ($line !== false)
                {
                        $line = trim($line);
                        $result_string .= "$line\n";
                        $line = get_one_line($buffer);
                }

                format_output($result_string, $report_stage['descr']);
                $result_string = '';
        }

        device_disconnect();
}
catch (Exception | Error $e)
{
        device_disconnect();
        return $e->getCode();
}

return SMS_OK;

?>