<?php
/*
 * Version: $Id: cisco_restore_configuration.php 43100 2011-05-18 14:58:59Z oda $ Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
// NSP Bugfix 2017.07.28 MOD START
// Modified Device Adaptor Name
require_once load_once ( 'hp2530', 'hp2530_connect.php' );
require_once load_once ( 'hp2530', 'apply_errors.php' );
require_once "$db_objects";

class hp2530_restore_configuration {
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
	function __construct($sdid) {
		$this->sdid = $sdid;


		$net = get_network_profile ();
		$this->sd = &$net->SD;
	}

	function generate_from_old_revision($revision_id) {
		echo ("generate_from_old_revision revision_id: $revision_id\n");
		$this->revision_id = $revision_id;

		$get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
		echo ($get_saved_conf_cmd . "\n");

		$ret = exec_local ( __FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output );
		if ($ret !== SMS_OK) {
			echo ("no running conf found\n");
			return $ret;
		}

		$output = array_filter( $output );
		
		$res = array_to_string ( $output );
		$this->runningconf_to_restore = $res;

		return SMS_OK;

	}

	function restore_conf() {
		global $apply_errors;

		global $sms_sd_ctx;
		$ret = SMS_OK;
		// Request flash space on router
		$file_name = "{$this->sdid}.cfg";
		$full_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;



		$ret = save_file ( $this->runningconf_to_restore, $full_name );
		if ($ret !== SMS_OK) {
				return $ret;
		}

		$ret = save_result_file ( $this->runningconf_to_restore, 'conf.applied' );
		if ($ret !== SMS_OK) {
				return $ret;
		}
		echo "tftp mode configuration\n";
		$ret = SMS_OK;
		$sms_ip_addr = $_SERVER ['SMS_ADDRESS_IP'];

		//sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp://$sms_ip_addr/$file_name startup-config", "]?" );
		##############################################
		// load the data and delete the line from the array 
		$lines = file($full_name); 
		$last = sizeof($lines) - 1 ; 
		unset($lines[$last]); 

		// write the new data to the file 
		$fp = fopen($full_name, 'w'); 
		fwrite($fp, implode('', $lines)); 
		fclose($fp); 
		//END
		###############################################
		
		sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy tftp startup-config $sms_ip_addr $file_name", "]?" );
		$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'y');

	//	func_reboot ( 'restore configuration' ); //removed to prevent reboot

		return $ret;
	}

	function wait_until_device_is_up() {
		return wait_for_device_up ( $this->sd->SD_IP_CONFIG );
	}
}

?>
