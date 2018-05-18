<?php

// Get generated configuration for the router
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('vmware_vsphere', 'vmware_vsphere_configuration.php');

try
{
  $generated_configuration = '';
  
  $conf = new vmware_vsphere_configuration($sdid);
  
  $ret = $conf->build_conf($generated_configuration);
  
  if ($ret !== SMS_OK)
  {
    return $ret;
  }
  
  sms_send_user_ok($sms_csp, $sdid, $generated_configuration);
}
catch (Exception $e)
{
  sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}
return SMS_OK;

?>