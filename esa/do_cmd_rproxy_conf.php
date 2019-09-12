<?php
/*
* Version: $Id$
* Created: Jun 29, 2017
* Available global variables
*  $sms_sd_info   sd_info structure
*  $sms_csp       pointer to csp context to send response to user
*  $sdid          id of the device
*  $optional_params	  optional parameters
*  $sms_module    module name (for patterns)
*/

// Verb JSACMD RPROXYCONF
require_once 'smsd/sms_common.php';
require_once load_once('smsd', 'do_cmd_rproxy_conf.php');
return SMS_OK;

?>