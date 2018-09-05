<?php
/*
 * Version: $Id: do_checkprovisioning.php 53274 2012-01-17 14:56:28Z ees $
 * Created: Jun 27, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 */

// Verb CHECKPROVISIONING


require_once 'smsd/sms_common.php';

require_once load_once('mon_generic', 'provisioning_stages.php');

return require_once 'smsd/do_checkprovisioning.php';

?>