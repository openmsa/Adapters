<?php
/*
 * Version: $Id$
 * Created: Apr 28, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 * 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 * 	$SMS_RETURN_BUF     string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'cmd_create.php');
require_once load_once('smsd', 'cmd_read.php');
require_once load_once('smsd', 'cmd_update.php');
require_once load_once('smsd', 'cmd_delete.php');
require_once load_once('smsd', 'cmd_import.php');
require_once load_once('smsd', 'cmd_list.php');
require_once load_once('linux_generic', 'adaptor.php');

require_once load_once('smsd', 'generic_command.php');
require_once load_once('smsd', 'linux_generic_command.php');


?>
