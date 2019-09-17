<?php
/*
 * Version: $Id$
 * Created: Jun 27, 2008
 * Available global variables
 *  $sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 */

// Enter Script description here



$provisioning_stages = array(
0 => array('name' => "Icmp testing"),
1 => array('name' => "Snmp testing"),
2 => array('name' => "DNS update"),
);

?>