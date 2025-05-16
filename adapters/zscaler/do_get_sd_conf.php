<?php

require_once 'smsd/sms_common.php';

sms_send_user_error($sms_csp, $sdid, "", ERR_SD_NOT_SUPPORTED);

return SMS_OK;

?>
