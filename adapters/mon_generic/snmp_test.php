<?php
/*
 * Version: $Id$
 * Created: May 30, 2008
 * Available global variables
 *  $sms_csp         pointer use for user message
 *  $snmp_cmd        snmp command 'get' or 'walk'
 * 	$ip_address      ip address
 *  $snmp_community  snmp community
 *  $oid             oid
 *  $sdid            empty
 */

// Snmp test

require_once 'smsd/sms_common.php';

//$ret = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/snmp_test.sh -t $snmp_cmd -H $ip_address -v 2c -c $snmp_community -o $oid", $output);
$ret = exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/snmp_test.sh -t $snmp_cmd -H $ip_address -v 3 -a MD5 -A ipcore123 -l authPriv -n ipcore -x DES -X ipcore123 -o $oid", $output);
if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, implode(' ' ,$output), ERR_SD_SNMP);
}
else
{
  sms_send_user_ok($sms_csp, $sdid, '');
}

sms_close_user_socket($sms_csp);

return SMS_OK;
?>
