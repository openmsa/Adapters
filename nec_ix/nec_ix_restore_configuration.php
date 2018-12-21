<?php
/*
 * Version: $Id: cisco_restore_configuration.php 43100 2011-05-18 14:58:59Z oda $ Created: Feb 12, 2009
 * Created: Dec 06, 2018
 */
require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once load_once ( 'nec_ix', 'nec_ix_connect.php' );
require_once load_once ( 'nec_ix', 'apply_errors.php' );
require_once load_once ( 'nec_ix', 'common.php' );
require_once "$db_objects";

class nec_ix_restore_configuration {
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

                sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf", '(config)#');
		//sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy $sms_ip_addr:$file_name startup-config", "]?" );
		sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "copy $sms_ip_addr:$file_name startup-config", '(config)#');
		//$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'y');
                sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", $sms_sd_ctx->getPrompt());

                func_reboot ( 'restore configuration' ); //removed to prevent reboot

		return $ret;
	}

	function wait_until_device_is_up() {
		return wait_for_device_up ( $this->sd->SD_IP_CONFIG );
	}
}

?>
