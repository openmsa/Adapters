<?php
/*
 * Version: $Id: do_cmd_loghdf2zip.php 50 2016-07-07 14:05:47Z domeki $
 * Created: Jun 26, 2014
 * Available global variables
 * $sms_sd_info sd_info structure
 * $sms_csp pointer to csp context to send response to user
 * $sdid id of the device
 * $optional_params optional parameters
 * $sms_module module name (for patterns)
 */

// Verb JSACMD XXX
require_once 'smsd/sms_common.php';
require_once load_once('smsd', 'do_cmd_loghdf2zip.php');
return SMS_OK;

?>
