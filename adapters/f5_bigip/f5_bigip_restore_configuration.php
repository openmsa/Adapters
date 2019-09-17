<?php

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';

require_once load_once('f5_bigip', 'common.php');

require_once "$db_objects";

class f5_bigip_restore_configuration
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
  var $spool_folder;        // folder where to generate the conf
  var $archivefile_to_restore;             //conf archive retrieved from SVN
  var $backup_type;         // backup file type (ucs / scf)

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

    $this->backup_type = "scf";
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */

  function generate_from_old_revision($revision_id)
  {
    echo("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;
    $this->spool_folder = "/opt/sms/spool/tmp";
    $this->archivefile_to_restore = "{$this->spool_folder}/{$this->sdid}_r{$this->revision_id}.{$this->backup_type}";

    if($this->backup_type === "ucs")
    {
        $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --getfile {$this->sdid} ucs {$this->archivefile_to_restore} r{$this->revision_id}";
    }
    else    // $this->backup_type === "scf"
    {
        $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get {$this->sdid} r{$this->revision_id}";
    }
    echo($get_saved_conf_cmd."\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__,  $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK) {
      echo("no running conf found\n");
      return $ret;
    }

    if($this->backup_type === "ucs")
    {
        if (!file_exists($this->archivefile_to_restore))
        {
          echo("no running conf found\n");
          return ERR_CONFIG_EMPTY;
        }
    }
    else    // $this->backup_type === "scf"
    {
        $res=array_to_string($output);
        $this->runningconf_to_restore = $res;
        $this->runningconf_to_restore = str_replace("SMS_OK", "", $this->runningconf_to_restore);
    }

    return SMS_OK;
  }


  function restore_conf()
  {
    global $sms_sd_ctx;

    $ret = SMS_OK;

    $filename = "{$_SERVER['TFTP_BASE']}/{$this->sdid}.{$this->backup_type}";

    if($this->backup_type === "ucs")
    {
        copy("{$this->archivefile_to_restore}", "{$filename}");
        unlink("{$this->archivefile_to_restore}");
    }
    else    // $this->backup_type === "scf"
    {
        file_put_contents($filename, $this->runningconf_to_restore);
    }

    if(empty($this->sd->SD_CONFIGVAR_list['MANAGEMENT_VLAN_IP']))
    {
        $tftp_ip = $_SERVER['SMS_ADDRESS_IP'];
    }
    else
    {
        $tftp_ip = $this->sd->SD_CONFIGVAR_list['MANAGEMENT_VLAN_IP']->VAR_VALUE;
    }

    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "cd /var/local/{$this->backup_type}", "#");
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "tftp {$tftp_ip}", "tftp>");
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "get {$this->sdid}.{$this->backup_type}", "tftp>");
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "quit", "#");

    if($this->backup_type === "ucs")
    {
        $restore_cmd = "tmsh load sys ucs {$this->sdid}.ucs";
    }
    else    // $this->backup_type === "scf"
    {
        $restore_cmd = "tmsh load sys config file {$this->sdid}.scf";
    }

    unset($tab);
    $tab[0] = "#";
    $tab[1] = "(y/n)";
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "{$restore_cmd}", $tab);
    if ($index !== 0)
    {
        $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "y", "#");
    }

    unset($tab);
    $tab[0] = "#";
    $tab[1] = "(y/n)";
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "tmsh save sys config", $tab);
    if ($index !== 0)
    {
        $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "y", "#");
    }

    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "rm -f {$this->sdid}*", "#");
    func_reboot();

    unlink($filename);

    return $ret;
  }


  function wait_until_device_is_up()
  {
    return wait_for_device_up($this->sd->SD_IP_CONFIG);
  }

}

?>
