<?php
/*
 * Version: $Id$
 * Created: Apr 28, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 * 	$sms_sd_ctx         pointer to sd_ctx context to retrieve usefull field(s)
 * 	$SMS_RETURN_BUF     string buffer containing the result
 */
require_once 'smsd/generic_command.php';

require_once load_once('stormshield', 'adaptor.php');

class stormshield_command extends generic_command
{
}
