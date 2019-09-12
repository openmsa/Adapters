<?php
/*
 * Version: $Id: do_staging.php 36331 2011-10-25 15:01:12Z cpi $
 * Created: Oct 24, 2011
 * Available global variables
 *  $sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_csp            pointer to csp context to send response to user
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Get generated configuration for the router
require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('oneaccess_lbb', 'oneaccess_lbb_configuration.php');
$script_file = "$sdid:".__FILE__;

try
{
        $conf = new oneaccess_lbb_configuration($sdid);
        $configuration = $conf->get_preconf();

        $result = sms_user_message_add("", SMS_UMN_CONFIG, $configuration);
        $user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
        $user_message = sms_user_message_add_json($user_message, SMS_UMN_RESULT, $result);

        sms_send_user_message($sms_csp, $sdid, $user_message);
}
catch(Exception | Error $e)
{
        sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;

?>
