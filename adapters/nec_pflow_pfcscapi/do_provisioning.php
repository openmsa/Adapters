<?php

require_once 'smsd/sms_common.php';

require_once load_once('nec_pflow_pfcscapi', 'adaptor.php');
require_once "$db_objects";


try {
  $network = get_network_profile();
  $SD = &$network->SD;
  if (empty($ipaddr))
  {
    $ipaddr = $SD->SD_IP_CONFIG;
  }
  if (empty($login))
  {
    $login = $SD->SD_LOGIN_ENTRY;
  }
  if (empty($passwd))
  {
    $passwd = $SD->SD_PASSWD_ENTRY;
  }

  // -------------------------------------------------------------------------------------
  // USER PARAMETERS CHECK
  // -------------------------------------------------------------------------------------
  if (empty($ipaddr) || empty($login) || empty($passwd))
  {
    sms_send_user_error($sms_csp, $sdid, "addr=$ipaddr login=$login pass=$passwd", ERR_VERB_BAD_PARAM);
    return SMS_OK;
  }

  // -------------------------------------------------------------------------------------
  // Set the provisioning stages
  // -------------------------------------------------------------------------------------
  require_once load_once('nec_pflow_pfcscapi', 'provisioning_stages.php');

  // Reset the provisioning status in the database
  // all the stages are marked "not run"
  $nb_stages = count($provisioning_stages);
  $ret = sms_bd_init_provstatus($sms_csp, $sms_sd_info, $nb_stages, $provisioning_stages);
  if ($ret)
  {
    sms_send_user_error($sms_csp, $sdid, "", $ret);
    sms_close_user_socket($sms_csp);
    return SMS_OK;
  }
  sms_send_user_ok($sms_csp, $sdid, "");
  sms_close_user_socket($sms_csp);

  // -------------------------------------------------------------------------------------
  // Asynchronous mode, the user socket is now closed, the results are written in database
  // -------------------------------------------------------------------------------------

  $stage = 0;
  $nb_stages -= 1;
  sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'W', 0, null, ""); // working status
  foreach ($provisioning_stages as $provisioning_stage)
  {
    $prog = $provisioning_stage['prog'];
    include_once load_once('nec_pflow_pfcscapi', "{$prog}.php");
    if (call_user_func_array($prog, array($sms_csp, $sdid, $sms_sd_info, $stage,$provisioning_stage)) !== SMS_OK)
    {
      // Error end of the provisioning
      return ERR_SD_CMDFAILED;
    }
    if ($stage === $nb_stages)
    {
      sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, null, ""); // End
    }
    else
    {
      sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'E', 0, 'W', ""); // End
    }
    $stage += 1;
  }

}
catch (Exception $e)
{
  sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $e->getCode(), null, $e->getMessage());
  return ERR_SD_CMDFAILED;
}

// End of the script
return SMS_OK;

?>

