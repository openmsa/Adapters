<?php
/*
 * Version: $Id$
 * Created: Dec 28, 2009
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $smsexec_list		list of commands
 */

// Enter Script description here

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

sms_send_user_error($sms_csp, $sdid, "", ERR_SD_NOT_SUPPORTED);

return SMS_OK;

?>