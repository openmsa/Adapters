<?php

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';

require_once load_once('a10_thunder', 'common.php');

require_once "$db_objects";

class a10_thunder_restore_configuration
{
  var $conf_path;           // Path for previous stored configuration files
  var $sdid;                // ID of the SD to update
  var $sd;                  // Current SD
  var $running_conf;        // Current configuration of the router
  var $previous_conf_list;  // Previous generated configuration loaded from files
  var $conf_list;           // Current generated configuration waiting to be saved
  var $addon_list;          // List of managed addon cards
  var $fmc_repo;            // repository path without trailing /
  var $fmc_ent;             // entities path without trailing /
  var $runningconf_to_restore;             //running conf retrieved from SVN /

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  */
  function __construct($sdid)
  {
    //$this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    //$this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    //$this->fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];

    $net = get_network_profile();
    $this->sd=&$net->SD;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */

  function generate_from_old_revision($revision_id)
  {
    echo("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd="/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
    echo($get_saved_conf_cmd."\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__,  $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK) {
       echo("no running conf found\n");
       return $ret;
    }

    $res=array_to_string($output);

    $this->runningconf_to_restore = $res;

    $this->runningconf_to_restore = str_replace("SMS_OK", "", $this->runningconf_to_restore);
    return SMS_OK;
  }


  function restore_conf()
  {
    global $sms_sd_ctx;

    $ret = SMS_OK;

    //$this->conf_to_restore
    $filename = "{$_SERVER['TFTP_BASE']}/{$this->sdid}.cfg";
    file_put_contents($filename, $this->runningconf_to_restore);

    $tab[0] = ')#';
    $tab[1] = 'yes/no';
    // config mode
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "configure terminal", $tab);
    if ($index == 1) {
        $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "yes", ")#");
    }

    if(empty($this->sd->SD_CONFIGVAR_list['MANAGEMENT_VLAN_IP']))
    {
        $tftp_ip = $_SERVER['SMS_ADDRESS_IP'];
    }
    else
    {
        $tftp_ip = $this->sd->SD_CONFIGVAR_list['MANAGEMENT_VLAN_IP']->VAR_VALUE;
    }

    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "copy tftp://{$tftp_ip}/{$this->sdid}.cfg startup-config", "[yes/no]");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "yes", "Please provide a profile name");

    unset($tab);
    $tab[0] = "File copied successfully";
    $tab[1] = "Backend Error";
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "startup-config", $tab);
    if ($index !== 0)
    {
        $SMS_OUTPUT_BUF = $sendexpect_result;
        $ret = ERR_RESTORE_FAILED;
    }

    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "end", "#");
    unlink($filename);

    func_reboot();

    return $ret;
  }


  function wait_until_device_is_up()
  {
    return wait_for_device_up($this->sd->SD_IP_CONFIG);
  }

}

?>
