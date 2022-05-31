<?php
/*
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 *  $sms_msg       message
 *  $config_type   type of configuration (CONF_FILE or CONF_TREE)
 */

// Verb JSABACKUPCONF

require_once 'smsd/sms_common.php';
require_once load_once('smsd', 'do_backup_conf.php');
return SMS_OK;

?>