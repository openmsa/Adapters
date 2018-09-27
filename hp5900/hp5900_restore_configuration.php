<?php
/*
 * Version: $Id: cisco_restore_configuration.php 43100 2011-05-18 14:58:59Z oda $ Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once load_once ( 'hp5900', 'hp5900_connect.php' );
require_once load_once ( 'hp5900', 'apply_errors.php' );

require_once "$db_objects";
class hp5900_restore_configuration {
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $sd; // Current SD
  var $running_conf; // Current configuration of the router
  var $previous_conf_list; // Previous generated configuration loaded from files
  var $conf_list; // Current generated configuration waiting to be saved
  var $addon_list; // List of managed addon cards
  var $fmc_repo; // repository path without trailing /
  var $fmc_ent; // entities path without trailing /
  var $runningconf_to_restore; // running conf retrieved from SVN /

  // ------------------------------------------------------------------------------------------------
  /**
   * Constructor
   */
  function __construct($sdid)
  {
    $this->sdid = $sdid;
    $net = get_network_profile ();
    $this->sd = &$net->SD;
  }

  function generate_from_old_revision($revision_id)
  {
    echo("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
    echo($get_saved_conf_cmd . "\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK)
    {
      echo("no running conf found\n");
      return $ret;
    }
    

    //[BUG#37] NSP Bugfix 2017.10.13 MODIFIED START
    //remove first line
    if($output[0] == "display current-configuration")
    {
	array_shift($output);	
    }
    //remove last line
    if(end($output) == "SMS_OK")
    {
    	array_pop($output);	
    }
    //BUG#37] NSP Bugfix 2017.10.13 MODIFIED END
    $res = array_to_string($output);
    $this->conf_to_restore = $res;

    return SMS_OK;
  }

  //NCOS Bugfix 2017.09.07 MODIFIED START
  function restore_conf()
  {
    global $sms_sd_ctx;

    $filename = "{$_SERVER['TFTP_BASE']}/{$this->sdid}.cfg";
    file_put_contents($filename, $this->conf_to_restore);
    
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "copy tftp://{$_SERVER['SMS_ADDRESS_IP']}/{$this->sdid}.cfg startup.cfg", "/startup.cfg?[Y/N]:");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", "Overwrite it?[Y/N]:");
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", $sms_sd_ctx->getPrompt());
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "startup saved-configuration startup.cfg main", $sms_sd_ctx->getPrompt());

    $this->reboot();

    return SMS_OK;
  }

  function reboot()
  {
    global $sms_sd_ctx;

    unset($tab);
    $tab[0] = "reboot the device. Continue? [Y/N]:";
    $tab[1] = "save current configuration? [Y/N]:";

    $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "reboot", $tab);
    if ($index === 1)
    {
      sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "n", $tab[0]);
    }
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", "Now rebooting, please wait...");
    
    return SMS_OK;
  }
  //NCOS Bugfix 2017.09.07 MODIFIED END
  
  function wait_until_device_is_up()
  {
    return wait_for_device_up ( $this->sd->SD_IP_CONFIG );
  }
}

?>
