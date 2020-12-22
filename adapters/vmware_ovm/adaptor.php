<?php
/*
 * Version: $Id$
 * Created: May 30, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('linux_generic', 'adaptor.php');

?>