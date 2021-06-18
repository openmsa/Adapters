<?php
/*
 * Version: $Id: cisco_restore_configuration.php 43100 2011-05-18 14:58:59Z oda $
* Created: Feb 12, 2009
*/

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once load_once('catalyst_ios', 'common.php');
require_once load_once('catalyst_ios', 'catalyst_connection.php');
require_once load_once('catalyst_ios', 'apply_errors.php');

require_once "$db_objects";

class catalyst_ios_restore_configuration
{
  var $conf_path;           // Path for previous stored configuration files
  var $sdid;                // ID of the SD to update
  var $sd;                	// Current SD
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
    $this->sdid = $sdid;
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
    $patterns = array();
    $patterns[0] = '/enable secret 5\s*\S*\s*\n/';
    $patterns[1] = "/username\s+\S+\s+password[ ]+\S*[ ]*\S*[ ]*\S*[ ]*[ ]*\S*[ ]*\S*\n/";
    $patterns[2] = "/OK\s/";
    $patterns[3] = "/Current configuration+.*\n/";

    $replacements = array();
    $replacements[0] = "!\n";
    $replacements[1] = "!\n";
    $replacements[2] = "!";
    $replacements[3] = "!\n";

    $this->runningconf_to_restore = preg_replace($patterns, $replacements, $res);

    $enable_line = "enable secret " . $this->sd->SD_PASSWD_ADM . "\n";
    $username_line = "username " . $this->sd->SD_LOGIN_ENTRY . " password " . $this->sd->SD_PASSWD_ENTRY . "\n \n";
    $this->runningconf_to_restore = $enable_line . $username_line . $this->runningconf_to_restore;

    return SMS_OK;
  }


  function restore_conf()
  {
    global $apply_errors;

    global $sms_sd_ctx;
    $ret = SMS_OK;

    echo "SCP mode configuration\n";

    // Request flash space on router
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

    try{
	    $ret = scp_to_router($full_name, $file_name);
	    if ($ret === SMS_OK)
	    {
	      // CHECK if 'flash:' disk type exists in the Catalyst IOS device.
              $tab[0] = $sms_sd_ctx->getPrompt();
              $tab[1] = "#";
              $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'copy ?', $tab, DELAY);
              $SMS_OUTPUT_BUF_HELP = $sendexpect_result;

              $disk_type = '';
              if (preg_match('/\s+flash:/', $SMS_OUTPUT_BUF_HELP)) {
                $disk_type = 'flash:';
              }
	
	      // SCP OK
        $SMS_OUTPUT_BUF = copy_to_running("copy " . $disk_type . $file_name . " startup-config");
	      save_result_file($SMS_OUTPUT_BUF, "conf.error");

	      foreach ($apply_errors as $apply_error)
	      {
	        if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
	        {
	          sms_log_error(__FILE__.':'.__LINE__.": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
	          return ERR_SD_CMDFAILED;
	        }
	      }

	      unset($tab);
	      $tab[0] = $sms_sd_ctx->getPrompt();
	      $tab[1] = "]?";
	      $tab[2] = "[confirm]";
	      $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "delete " . $disk_type . $file_name, $tab);
	      while ($index !== 0)
	      {
	        $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "", $tab);
	      }

	      func_reboot('restore configuration');

	      return $ret;
	    }
	    else
	    {
	      // SCP ERROR
	      sms_log_error(__FILE__.':'.__LINE__.":SCP Error $ret\n");
	      return $ret;
	    }
     }
     catch ( Exception | Error $e ) {
     	if (strpos ( $e->getMessage (), 'connection failed' ) !== false) {
     		return ERR_SD_CONNREFUSED;
     	}
     	sms_log_error ( __FILE__ . ':' . __LINE__ . ":SCP Error $ret\n" );
     }


    echo "tftp mode configuration\n";

    $ret = SMS_OK;
    $sms_ip_addr = $_SERVER ['SMS_ADDRESS_IP'];

    sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/$file_name startup-config", "]?" );

    $SMS_OUTPUT_BUF = copy_to_running ( '' );
    save_result_file ( $SMS_OUTPUT_BUF, "conf.error" );

    foreach ( $apply_errors as $apply_error ) {
    	if (preg_match ( $apply_error, $SMS_OUTPUT_BUF ) > 0) {
    		sms_log_error ( __FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n" );
    		return ERR_SD_CMDFAILED;
    	}
    }

    if (! strpos ( $SMS_OUTPUT_BUF, 'bytes copied' )) {
    	sms_log_error ( __FILE__ . ':' . __LINE__ . ":tftp transfer failed\n" );
    	return ERR_SD_TFTP;
    }

    func_reboot ( 'restore configuration' );

    return $ret;
  }


  function wait_until_device_is_up()
  {
    return wait_for_device_up($this->sd->SD_IP_CONFIG);
  }

}

?>
