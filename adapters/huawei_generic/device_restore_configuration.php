<?php
/*
 * Version: $Id: cisco_restore_configuration.php 43100 2011-05-18 14:58:59Z oda $ Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once 'smsd/local_interactive_shell.php';
require_once load_once ( 'huawei_generic', 'device_connect.php' );
require_once load_once ( 'huawei_generic', 'apply_errors.php' );

require_once "$db_objects";
class device_restore_configuration 
{
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
		// $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
		$this->sdid = $sdid;
		// $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
		// $this->fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];

		$net = get_network_profile ();
		$this->sd = &$net->SD;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	 */
	function generate_from_old_revision($revision_id) 
	{
		echo ("generate_from_old_revision revision_id: $revision_id\n");
		$this->revision_id = $revision_id;

		$get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
		echo ($get_saved_conf_cmd . "\n");

		$ret = exec_local ( __FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output );
		if ($ret !== SMS_OK) 
		{
			echo ("no running conf found\n");
			return $ret;
		}
		array_pop($output);
		$res = array_to_string ( $output );
// 		$patterns = array ();
// 		$patterns [0] = '/enable secret 5\s*\S*\s*\n/';
// 		$patterns [1] = "/username\s+\S+\s+password[ ]+\S*[ ]*\S*[ ]*\S*[ ]*[ ]*\S*[ ]*\S*\n/";
// 		$patterns [2] = "/OK\s/";
// 		$patterns [3] = "/Current configuration+.*\n/";
// 		$replacements = array ();
// 		$replacements [0] = "#\n";
// 		$replacements [1] = "#\n";
// 		$replacements [2] = "#";
// 		$replacements [3] = "#\n";

		$this->runningconf_to_restore = $res;//preg_replace ( $patterns, $replacements, $res );
	
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
		$full_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;

		$ret = save_file ( $this->runningconf_to_restore, $full_name );
		if ($ret !== SMS_OK) 
		{
			return $ret;
		}
		$ret = save_result_file ( $this->runningconf_to_restore, 'conf.applied' );
		if ($ret !== SMS_OK) 
		{
			return $ret;
		}
		
		echo "tftp mode configuration\n";
		try
		{									
			$ret = SMS_OK;
			$sms_ip_addr = $_SERVER ['SMS_ADDRESS_IP'];
		
			// Switch to config mode
			sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, 'system-view');
			// Enable sftp server
			sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, 'sftp server enable' );
			sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, 'return', '>' );
			$login = $sms_sd_ctx->getLogin();
			$device_ip =  $sms_sd_ctx->getIpAddress();
			$password  = $sms_sd_ctx->getPassword();
						
			$altered_filename = time()."_".$file_name;		
			$ret = device_disconnect();
			echo "++++++++++after disconnect++++++++++++++\n";
			if ($ret !== SMS_OK)
			{
				return $ret;
			}
			//MSA sftp connection to huawei device
			echo "++++++++++before shell++++++++++++++\n";
			$shell = new LocalInteractiveShell();
			echo "++++++++++after shell++++++++++++++\n";
			$sftp_cmd = "/usr/bin/sftp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout=10 " . $login. "@" . $device_ip. "";
			// Call Sftp
			$shell->sendexpectone ( __FILE__ . ':' . __LINE__, $sftp_cmd, "password:" );
			$shell->sendexpectone ( __FILE__ . ':' . __LINE__,$password, ">" );
			// Push file to device
			
			
			$shell->sendexpectone ( __FILE__ . ':' . __LINE__, "put " . $full_name." ".$altered_filename, ">" );
			$shell->sendexpectone ( __FILE__ . ':' . __LINE__, "quit" ,"$");
			
			unset($shell);
			
			// connect to the devivce
			$ret = device_connect();
			if ($ret !== SMS_OK)
			{
				return $ret;
			}
			
			sendexpectone ( __FILE__ .  ':' . __LINE__, $sms_sd_ctx, "startup saved-configuration $altered_filename", ">");		
			
			echo "Rebooting System \n";
			echo "+++++++++++++++++++++++++++++++++++++\n";
		
 			// reboot device
			$tab = array();
			$tab[0] = "configuration. Continue ? [y/n]:";
			$tab[1] = "? [y/n]:";
			
			$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'reboot', $tab);
			//While system hasnt reached reboot stage loop
			while($index < 1)
			{
				switch ($index)
				{
					case 0:
						$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'y', $tab);
						break;
					case 1:
						$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'y', $tab);
						break;					
				}
				
			}
			return $ret;
			
		} 
		catch ( Exception | Error $e ) 
		{
			if (strpos ( $e->getMessage (), 'connection failed' ) !== false) 
			{
				return ERR_SD_TFTP;
			}
			sms_log_error ( __FILE__ . ':' . __LINE__ . ":tftp transfer failed\n" );
		}

		
	}
	
	function remove_old_config()
	{
		
		echo "===========REMOVE OLD CONFIG============\n";
		$ret = device_connect();
		if ($ret !== SMS_OK)
		{
			return $ret;
		}
		
		//Get Current Confignames		
		$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'display startup');
		if (preg_match('@^\s+ Startup saved-configuration file:\s+(?<file_name>\S+)\s*$@m', $buffer, $matches) > 0)
		{
			$device_config_file = explode("/",$matches['file_name']);
			$device_config_file= end($device_config_file);
			sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'delete '.$device_config_file,"[n]:");
			sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'y',">");
		}
		else
		{
			return $buffer;
		}
		
		$ret = device_disconnect();
		if ($ret !== SMS_OK)
		{
			return $ret;
		}
		
		return SMS_OK;
	}

	function wait_until_device_is_up() 
	{
		return wait_for_device_up ( $this->sd->SD_IP_CONFIG );
	}
}

?>