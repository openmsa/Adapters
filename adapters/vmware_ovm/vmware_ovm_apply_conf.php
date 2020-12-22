<?php
/*
 * Version: $Id$
 * Created: May 13, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('linux_generic', 'common.php');
require_once load_once('linux_generic', 'apply_errors.php');
require_once load_once('linux_generic', 'linux_generic_configuration.php');
require_once load_once('linux_generic', 'linux_generic_connect.php');
require_once load_once('linux_generic', 'linux_generic_apply_conf.php');

require_once "$db_objects";

?>

