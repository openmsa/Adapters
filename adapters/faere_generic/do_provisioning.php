<?php
/*
 * 	Version: $Id: do_provisioning.php 34480 2010-08-26 12:08:23Z tmt $
* 	Created: May 30, 2008
* 	Available global variables
*  	$sms_sd_info        sd_info structure
* 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*  	$sms_csp            pointer to csp context to send response to user
*  	$sdid
*  	$sms_module         module name (for patterns)
*  	$ipaddr             ip address of the router
*  	$login              current login
*  	$passwd             current password
*  	$adminpasswd        current administation **PORT**
*/

// Initial provisioning

require_once 'smsd/sms_common.php';

require_once load_once('faere_generic', 'adaptor.php');
require_once load_once('faere_generic', 'common.php');
require_once load_once('faere_generic', 'faere_generic_configuration.php');
require_once load_once('faere_generic', 'provisioning_stages.php');
require_once "$db_objects";


$is_ztd = false;

if (!empty($ipaddr))
{
  $is_ztd = true;
}

$network = get_network_profile();
$SD = &$network->SD;

if($SD->SD_MANAGEMENT_PORT !== 0)
{
  $port = $SD->SD_MANAGEMENT_PORT;
}
else
{
  $port = 22;
}

// -------------------------------------------------------------------------------------
// USER PARAMETERS CHECK
// -------------------------------------------------------------------------------------
if (empty($ipaddr) || empty($login) || empty($passwd)  || empty($port))
{
  sms_send_user_error($sms_csp, $sdid, "addr=$ipaddr login=$login pass=$passwd adminpass=$adminpasswd port=$port", ERR_VERB_BAD_PARAM);
  return SMS_OK;
}

return require_once 'smsd/do_provisioning.php';

?>