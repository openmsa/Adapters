<?php
/*
 * 	Version: $Id: do_provisioning.php 34480 2010-08-26 12:08:23Z tmt $
* 	Created: May 30, 2008
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

require_once load_once('linux_generic', 'adaptor.php');
require_once load_once('linux_generic', 'common.php');
require_once load_once('linux_generic', 'linux_generic_configuration.php');
require_once load_once('vmwa_ovm', 'provisioning_stages.php');

require_once "$db_objects";

return require_once 'linux_generic/do_provisioning.php';

?>