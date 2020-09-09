<?php
/*
 * Version: $Id$
* Created: Sep 30, 2010
* Available global variables
*  $sms_sd_info   sd_info structure
*  $sms_csp       pointer to csp context to send response to user
*  $sdid          id of the device
*  $optional_params	  optional parameters
*  $sms_module    module name (for patterns)
*/

// Verb JSACMD REBOOT

require_once 'smsd/sms_common.php';

require_once load_once('fujitsu_ipcom', 'fujitsu_ipcom_connect.php');
require_once load_once('fujitsu_ipcom', 'fujitsu_ipcom_configuration.php');
require_once load_once('fujitsu_ipcom', 'apply_errors.php');
require_once "$db_objects";


$status_type = 'DELETEROUTERFILE';

$net_profile = get_network_profile();
$sd = &$net_profile->SD;

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'WORKING', '');

// Asynchronous mode, the user socket is now closed, the results are written in database
sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

$on_error_fct = 'fujitsu_ipcom_exit';

$conf = new fujitsu_ipcom_configuration($sdid);


$status_message = "";
$ret = $conf->delete_router_file($status_type, $optional_params);

fujitsu_ipcom_disconnect(true);

if ($ret !== SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return $ret;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, $status_type, 'ENDED', $status_message);

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;

function fujitsu_ipcom_exit()
{
  global $sms_csp;
  global $sms_sd_info;
  global $sdid;
  global $status_type;

  sms_set_update_status($sms_csp, $sdid, ERR_SD_TFTP, $status_type, 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  fujitsu_ipcom_disconnect(true);
}

?>
