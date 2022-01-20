<?php
/*
* 	Available global variables
*  	$sms_sd_info        sd_info structure
* 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*  	$sms_csp            pointer to csp context to send response to user
*  	$sdid
*  	$sms_module         module name (for patterns)
*  	$ipaddr             ip address of the router
*  	$login              current login
*  	$passwd             current password
*  	$adminpasswd        current administation **PORT**
*/

// Initial provisioning

require_once 'smsd/sms_common.php';

require_once load_once('inventory_management', 'provisioning_stages.php');

return require_once 'smsd/do_provisioning.php';

?>

