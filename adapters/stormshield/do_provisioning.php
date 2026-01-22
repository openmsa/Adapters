<?php
/*
 * Version: $Id: do_provisioning.php 23451 2009-11-02 12:39:24Z tmt $
 * Created: May 30, 2008
 * Available global variables
 *  $sms_sd_info        sd_info structure
 * 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $ipaddr             ip address of the router
 *  $login              current login
 *  $passwd             current password
 *  $adminpasswd        current administrative password
 */

// Initial provisioning

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'adaptor.php');
require_once load_once('stormshield', 'provisioning_stages.php');


// -------------------------------------------------------------------------------------
// USER PARAMETERS CHECK
// -------------------------------------------------------------------------------------
if (empty($ipaddr) || empty($login) || empty($passwd))
{
  sms_send_user_error($sms_csp, $sdid, '', ERR_VERB_BAD_PARAM);
  return SMS_OK;
}

return require_once 'smsd/do_provisioning.php';

?>