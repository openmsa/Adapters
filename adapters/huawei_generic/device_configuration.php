<?php
/*
 * Version: $Id: device_configuration.php 58927 2012-06-11 15:15:18Z abr $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/local_interactive_shell.php';
require_once load_once('huawei_generic', 'device_apply_conf.php');
require_once "$db_objects";


class device_configuration
{
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $sd;
  var $fmc_repo; // repository path without trailing /
  

  // ------------------------------------------------------------------------------------------------
  /**
   * Constructor
   */
  function __construct($sdid, $is_provisionning = false)
  {
    $this->sdid = $sdid;
    $profile = get_network_profile();
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $this->sd = $profile->SD;
    $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Get running configuration from the router
   */
  function get_running_conf()
  {
    global $sms_sd_ctx;

    $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "display current-configuration",'return');
    $running_conf = remove_line_starting_with($running_conf,"display current-configuration");
    $running_conf = substr($running_conf,0 ,strpos($running_conf, 'return')+strlen('return'));
    $this->running_conf = $running_conf;
    return $this->running_conf;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
   function update_conf()
   {
     $ret = $this->build_conf($generated_configuration);

     if(!empty($generated_configuration))
     {
       $ret = device_apply_conf($generated_configuration);
     }

     return $ret;
   }

   // ------------------------------------------------------------------------------------------------
   /**
   *
   */
   function provisioning()
   {
     return $this->update_conf();
   }

   // ------------------------------------------------------------------------------------------------
   /**
    * Generate the general pre-configuration
    * @param $configuration   configuration buffer to fill
    */
    function generate_pre_conf(&$configuration)
    {
      get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_CONFIG', 'Configuration');
      return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
    * Generate a full configuration
    * Uses the previous conf if present to perform deltas
    */
    function generate(&$configuration, $use_running = false)
    {
      $configuration .= '';
      return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
    * Generate the general post-configuration
    * @param $configuration   configuration buffer to fill
    */
    function generate_post_conf(&$configuration)
    {
      get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
      return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
    *
    */
    function build_conf(&$generated_configuration)
    {

      $ret = $this->generate_pre_conf($generated_configuration);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
      $ret = $this->generate($generated_configuration);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
      $ret = $this->generate_post_conf($generated_configuration);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }

      return SMS_OK;
    }
    
    // ------------------------------------------------------------------------------------------------
    /**
     *
     */
	//  TODO: fully write function find out how to call relevant functions
    function update_firmware(&$param)
    {
    	global $status_message;
    	global $sms_sd_ctx;
    	
    	// Find File to transfer
    	status_progress('Checking firmware file', 'FIRMWARE');

    	// Get firmware
    	$ret = get_repo_files_map($map_conf, $error, 'Firmware');
    	if ($ret !== SMS_OK)
    	{
    		// xml entity file broken
    		return $ret;
    	}
    	
    	if (!empty($map_conf))
    	{
    		foreach ($map_conf as $mkey => $file)
    		{
    			if (!empty($file))
    			{
    				$firmware_file = "{$this->fmc_repo}/$file";
    				break; // use this first file found
    			}
    		}
    	}
    
    	if (empty($firmware_file))
    	{
    		sms_log_info(__FUNCTION__ . ": no file specified.\n");
    		$status_message = "No file specified";
    		return SMS_OK;
    	}
    	
    
    	
    	// Connect to the device
    	$ret = device_connect();
    	if ($ret !== SMS_OK)
    	{
    		return $ret;
    	}
    	$original_firmware_file= "";
    	
    	//check firmware currently in use
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'display startup');
    	if (preg_match('@^\s+Startup system software:\s+(?<file_name>\S+)\s*$@m', $buffer, $matches) > 0)
    	{
    		$original_firmware_file= $matches['file_name'];
    		$device_firmware_file = explode("/",$matches['file_name']);
    		$device_firmware_file = end($device_firmware_file);
    		
    		if($device_firmware_file == basename($firmware_file))
    		{
    			sms_log_info ( __FUNCTION__ . ": Firmware already installed.\n" );
    			$status_message = "Firmware already installed";
    			return SMS_OK;
    		}
    		sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'startup system-software '.$device_firmware_file.' backup');
    	}
    	
    	// Get file size
    	$firmware_size = filesize($firmware_file);
    	
    	// Check if firmware on device and firmware on file are the same
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'dir');
    	$device_firmware_filesize = 0;
    	if (preg_match('@^\s+\S+\s+\S+\s+(?<size>\S+).*\s+'.basename($firmware_file).'\s*$@m', $buffer, $matches) > 0)
    	{
    		$device_firmware_filesize  = str_replace(',', '', $matches['size']);
    	}
			
		// Check disk space available
		if (preg_match ( '#\((?<size>\S+) KB free\)#', $buffer, $matches ) > 0)
		{
			$device_available_space = (str_replace ( ',', '', $matches ['size'] )* 1000) + $device_firmware_filesize ;
			if ($device_available_space < $firmware_size) 
			{
				sms_log_info ( __FUNCTION__ . ": insufficient disk space on device.\n" );
				$status_message = "Insufficient disk space on device";
				return ERR_SD_ENOMEM;
			}
		} 
		else 
		{
			sms_log_info ( __FUNCTION__ . ": Unknown diskpace on device.\n" );
			$status_message = "Unknown diskpace on device";
			return ERR_SD_ENOMEM;
		}
		
		status_progress ( 'Trasnfering firmware file', 'FIRMWARE' );
		// Switch to config mode
		sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, 'system-view');
		// Enable sftp server
		sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, 'sftp server enable' );
		
		$login = $sms_sd_ctx->getLogin();
		$device_ip =  $sms_sd_ctx->getIpAddress();
		$password  = $sms_sd_ctx->getPassword();
		
		$ret = device_disconnect ();
		if ($ret !== SMS_OK) 
		{
			return $ret;
		}
		
		$shell = new LocalInteractiveShell();
		$sftp_cmd = "/usr/bin/sftp -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 -o ConnectTimeout=10 " . $login. "@" . $device_ip. "";
		// Call Sftp 
		$shell->sendexpectone ( __FILE__ . ':' . __LINE__, $sftp_cmd, "password:" );
		$shell->sendexpectone ( __FILE__ . ':' . __LINE__,$password, ">" );
		// Push file to device
		$shell->sendexpectone ( __FILE__ . ':' . __LINE__, "put " . $firmware_file, ">" );
		$shell->sendexpectone ( __FILE__ . ':' . __LINE__, "quit" ,"$");
		
		unset($shell);

    	// connect to the devivce
    	$ret = device_connect();
    	if ($ret !== SMS_OK)
    	{
    		return $ret;
    	}
    	
    	// confirm file trasnfered correctly
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'dir');
    	$device_firmware_filesize = 0;
    	if (preg_match('@^\s+\S+\s+\S+\s+(?<size>\S+).*\s+'.basename($firmware_file).'\s*$@m', $buffer, $matches) > 0)
    	{
    		$device_firmware_filesize  = str_replace(',', '', $matches['size']);
    	}
    	
    	if($device_firmware_filesize != $firmware_size )
    	{
    		sms_log_info ( __FUNCTION__ . ": Firmware file transfer failed.\n" );
    		$status_message = "Firmware file transfer failed";
    		return ERR_SD_FILE_TRANSFER;
    	} 
    	$tab = array();
    	$tab[0] = "Succeeded";
    	$tab[1] = "Error";
    	
    	// updrage firmware
    	status_progress('Verifying the firmware', 'FIRMWARE');
    	$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'startup system-software '.basename($firmware_file).' verify', $tab);
    	if($index == 1)
    	{
    		sms_log_info ( __FUNCTION__ . ": Invalid firmware file.\n" );
    		
    		$status_message = "Invalid firmware file";
    		return ERR_FIRMWARE_CORRUPTED;
    	}
    	status_progress('Rebooting Device', 'FIRMWARE');
    	// reboot device
    	$tab = array();
    	$tab[0] = "Warning";
    	$tab[1] = "System will reboot!";
    	$tab[2] = "system is rebooting";
    	$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'reboot', $tab);
    	
    	
    	//While system hasnt reached reboot stage loop
    	while($index < 2)
    	{
	    	switch ($index)
	    	{
	    		case 0:
	    			$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'n', $tab);
	    			break;
	    		case 1:
	    			$index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'y', $tab);
	    			break;
	    	}
    		
    	}
    	
    	//sleep to give the device time to apply the new firmware
    	$ret = wait_for_device_up($sms_sd_ctx->getIpAddress());
    	if($ret != SMS_OK)
    	{
    		return $ret;
    	}
    	
    	status_progress('Connecting to the device', 'FIRMWARE');
    	$loop = 5;
    	while ($loop > 0)
    	{
    		sleep(10); // wait for ssh to come up
    		$ret = device_connect();
    		if ($ret == SMS_OK)
    		{
    			break;
    		}
    		$loop--;
    	}
    	
    	if ($ret != SMS_OK)
    	{
    		return $ret;
    	}
    	status_progress('Checking the firmware currently used', 'FIRMWARE');
    	//check firmware currently in use
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'display startup');
    	
    	if (preg_match('@^\s+Startup system software:\s+(?<file_name>\S+)\s*$@m', $buffer, $matches) > 0)
    	{
    		$device_firmware_file = explode("/",$matches['file_name']);
    		$device_firmware_file = end($device_firmware_file);
    		
    		if($device_firmware_file == basename($firmware_file))
    		{
    			sms_log_info ( __FUNCTION__ . ": Firmware correctly installed.\n" );
    			$status_message = "Firmware correctly installed";
    			return SMS_OK;
    		}
    	}
    	//remove old firmware file from the device
//     	sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'delete '.$original_firmware_file);
    	status_progress('Succesfully Upgraded Firmware', 'FIRMWARE');
    	
    	return SMS_OK;
    }
}

?>
