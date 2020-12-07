<?php

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
//require_once 'hpe_redfish_connect.php';

require_once "$db_objects";

class device_restore_configuration
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
    global $apply_errors;

    global $sms_sd_ctx;
    $ret = SMS_OK;

    $file_name = "{$this->sdid}.cfg";
    $full_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;

    $ret = save_file($this->runningconf_to_restore, $full_name);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = save_result_file($this->runningconf_to_restore, 'conf.applied');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
	$ret = $sms_sd_ctx->send_file($this->sdid, $full_name);
	echo $ret;
    return $ret;
  }


  function wait_until_device_is_up()
  {
    return wait_for_device_up($this->sd->SD_IP_CONFIG);
  }

}

?>