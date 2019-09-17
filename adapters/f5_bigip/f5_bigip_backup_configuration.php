<?php

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';

require_once load_once('f5_bigip', 'common.php');

require_once "$db_objects";

class f5_bigip_backup_configuration
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

  function backup_conf()
  {
    global $sms_sd_ctx;

    if($this->backup_type === "ucs")
    {
        $backup_cmd = "tmsh save sys ucs {$this->sdid}.ucs";
    }
    else    // $this->backup_type === "scf"
    {
        // #TMSH-VERSION: 12.0.0
        // $backup_cmd = "tmsh save sys config file {$this->sdid}.scf";

        // #TMSH-VERSION: 12.1.2
        $backup_cmd = "tmsh save sys config file {$this->sdid}.scf no-passphrase";
    }

    unset($tab);
    $tab[0] = "#";
    $tab[1] = "(y/n)";
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "{$backup_cmd}", $tab);
    if ($index !== 0)
    {
        $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "y", "#");
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
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "put {$this->sdid}.{$this->backup_type}", "tftp>");
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "quit", "#");
    $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, "rm -f {$this->sdid}*", "#");

    clearstatcache(TRUE, "{$_SERVER['TFTP_BASE']}/{$this->sdid}.{$this->backup_type}");
    if (!file_exists("{$_SERVER['TFTP_BASE']}/{$this->sdid}.{$this->backup_type}"))
    {
        return ERR_SD_FAILED;
    }

    return SMS_OK;
  }

  function get_return_buf()
  {
    if($this->backup_type === "ucs")
    {
        $archive_conf_path = "/opt/sms/spool/routerconfigs/{$this->sdid}/conf.ucs";

        copy("{$_SERVER['TFTP_BASE']}/{$this->sdid}.ucs", "{$archive_conf_path}");
        unlink("{$_SERVER['TFTP_BASE']}/{$this->sdid}.ucs");

        $date = date('c');
        $return_buf = "{$date}: Configuration is in {$archive_conf_path}";
    }
    else    // $this->backup_type === "scf"
    {
        $return_buf = file_get_contents("{$_SERVER['TFTP_BASE']}/{$this->sdid}.scf");
        unlink("{$_SERVER['TFTP_BASE']}/{$this->sdid}.scf");
    }

    return $return_buf;
  }

}

?>
