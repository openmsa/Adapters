<?php


// Initial provisioning

require_once 'smsd/sms_common.php';

require_once load_once('cisco_ios_xr', 'adaptor.php');
require_once load_once('cisco_ios_xr', 'common.php');
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_configuration.php');
require_once load_once('cisco_ios_xr', 'provisioning_stages.php');
require_once "$db_objects";


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
if (empty($ipaddr) || empty($login) || empty($passwd) || empty($port))
{
  sms_send_user_error($sms_csp, $sdid, "addr=$ipaddr login=$login pass=$passwd adminpass=$adminpasswd port=$port", ERR_VERB_BAD_PARAM);
  return SMS_OK;
}

return require_once 'smsd/do_provisioning.php';

?>