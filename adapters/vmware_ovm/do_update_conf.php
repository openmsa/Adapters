<?php
/*
 * Version: $Id: do_update_conf.php 38235 2010-12-27 08:49:22Z tmt $
* Created: Jun 30, 2008
* Available global variables
* 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info        sd_info structure
*  $sms_csp            pointer to csp context to send response to user
*  $sdid
*  $sms_module         module name (for patterns)
* 	$SMS_RETURN_BUF    string buffer containing the result
*
*/

// Enter Script description here

require_once 'smsd/sms_common.php';
require_once load_once('linux_generic', 'linux_generic_connect.php');
require_once load_once('linux_generic', 'linux_generic_configuration.php');
require_once load_once('linux_generic', 'common.php');

return require_once 'linux_generic/do_update_conf.php';

?>