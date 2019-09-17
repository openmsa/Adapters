<?php
/*
 * Version: $Id: OneAccess_configuration.php 37371 2010-11-30 17:46:40Z tmt $
* Created: Feb 12, 2009
*/

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('oneaccess_lbb', 'common.php');
require_once load_once('oneaccess_lbb', 'adaptor.php');
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_apply_conf.php');

require_once "$db_objects";


/**
 * @addtogroup ciscoupdate  Cisco Routers Update Process
 * @{
 */

/** Main configuration manager
 * All the profile managed are listed in the constructor.
 */
class oneaccess_lbb_configuration
{
  var $conf_path;           // Path for previous stored configuration files
  var $sdid;                // ID of the SD to update
  var $running_conf;        // Current configuration of the router
  var $revision_id;
  var $net_conf;
  var $sd;
  var $fmc_repo;
  var $conf_pflid;

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  * The list of all the managed profiles is created here.
  * @param $sdid    SD ID of the current SD to configure
  * @param $is_provisionning    true if the update is for provisioning (more initializations)
  */
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->net_conf = get_network_profile();
    $this->sd = $this->net_conf->SD;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate the begining of the configuration
  */
  function generate_begin_conf()
  {
    $configuration = "";
    if($ret = get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_CONFIG', 'Configuration') !== SMS_OK)
    {
    		throw SmsException("generate_begin_conf failed with sdid: " . $this->sdid . ", pflid: " . $this->conf_pflid . "!" );
    }
    return $configuration;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate the end of the configuration
  */
  function generate_end_conf()
  {
    $configuration = "";
    if($ret = get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration') !== SMS_OK)
    {
      throw SmsException("generate_begin_conf failed with sdid: " . $this->sdid . ", pflid: " . $this->conf_pflid . "!" );
    }
    return $configuration;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate a full configuration
  * Uses the running conf if specified or previous conf if present to perform deltas
  */
  function generate()
  {
    return "";
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Save current configuration to file
  */
  /*function save_generated()
   {
  // TODO : sauvegarder les pre et les post ?
  return SMS_OK;
  }*/

  // ------------------------------------------------------------------------------------------------
  /**
  * Load a previous generated configuration
  */
  /*function load_previous_generated()
   {
  return SMS_OK;
  }*/

  // ------------------------------------------------------------------------------------------------
  /**
  * Get running configuration from the router
  */
  function get_running_conf()
  {
  	global $sms_sd_ctx;
    $this->running_conf = $sms_sd_ctx->sendexpectone(__LINE__, "show run");
    //filter content
    $this->running_conf = preg_replace("/\\\\r\\\\n/", "", $this->running_conf);
    return $this->running_conf;
  }

  function get_generated_conf($revision_id = NULL)
  {
    if(!isset($revision_id))
    {
      $generated_configuration = $this->generate_begin_conf();
      $generated_configuration .= $this->generate();
      $generated_configuration .= $this->generate_end_conf();
      return $generated_configuration;
    }

    echo("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    echo("/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id\n");

    $res="";
    $res=shell_exec("/opt/sms/script/get_saved_conf --get " . "$this->sdid" . " r" . "$this->revision_id");

    if (strcmp($res, '') === 0){
      sms_log_error("no running conf found\n");
      return SMS_OK;
    }

    $patterns = array();

    //SHOULD MATCH:
    //enable secret 5 $1$9008$GsTgFNtas61aKLmM6Fpg6.
    //enable secret 5
    // NOT ON SWITCH300: $patterns[0] = '/enable secret 5\s*\S*\s*\n/';

    //SHOULD MATCH:
    //username cisco password 7
    //xx (not matching)
    //username admin password 7 03055F060F019
    //username CVCmu59995 password kUdbcmNwftYNT1RD encrypted privilege 0
    //username cisco password dfeaf10390e560aea745ccba53e044ed level 15 encrypted
    //SHOULD NOT MATCH
    //username NCO-SCP privilege 15 password 7 023E1D0E5A56597475
    //username cca privilege 15 password 7 0307580A
    //$patterns[0] = "/username\s+\S+\s+password[ ]+\S*[ ]*\S*[ ]*\S*[ ]*[ ]*\S*[ ]*\S*\n/";

    $patterns[0] = "/OK\s*/";
    $patterns[1] = "/Current configuration+.*\n/";

    $replacements = array();
    // NOT ON SWITCH300: $replacements[0] = "enable secret " . $this->sd->SD_PASSWD_ADM . "\n";
    //$replacements[0] = "username " . $this->sd_login_entry . " password " . $this->sd->SD_PASSWD_ENTRY . "\n";

    $replacements[0] = "!";
    $replacements[1] = "!\n";

    $generated_configuration = preg_replace($patterns, $replacements, $res);
    return $generated_configuration;
  }

  function get_preconf()
  {
    $configuration = '!<br>';
    $configuration .= '! The device must already have IP configuration enable.<br>';
    $configuration .= '!<br><br>';

    // 		$configuration .= '! SYSLOG configuraiton<br>';
    // 		$configuration .= 'logging host ' . $this->sd->SD_NODE_IP_ADDR . '<br>';
    // 		$configuration .= 'logging buffered 60 debugging<br>';
    // 		$configuration .= 'logging aggregation aging-time 15<br>';
    // 		$configuration .= 'logging on<br>';
    // 		$configuration .= '<br>';

    // 		$configuration .= '! Management Interface configuration<br>';
    // 		$configuration .= 'conf t<br>';
    // 		$configuration .= 'interface bvi 1<br>';
    // 		$configuration .= 'description Management IP<br>';
    // 		$configuration .= 'bridge-group 1<br>';
    // 		$configuration .= 'ip address '.$this->sd->SD_IP_CONFIG.' '.$this->sd->SD_INTERFACE_list['E']->INT_IP_MASK.'<br>';
    // 		$configuration .= 'exit<br>';

    // 		$configuration .= '! Default route configuration<br>';
    // 		$configuration .= 'ip route 0.0.0.0 0.0.0.0 '.$this->sd->SD_CONFIGVAR_list['defaultRouter']->VAR_VALUE.'<br>';

    $configuration .= '! SYSLOG configuraiton<br>';
    $configuration .= 'event filter add sys all syslog<br>';
    $configuration .= 'syslog server '.$this->sd->SD_NODE_IP_ADDR.' 23<br>';


    $configuration .= '! SNMP configuraiton<br>';
    $configuration .= 'snmp set-read-community '.$this->sd->SD_SNMP_COMMUNITY . '<br>';
    $configuration .= 'snmp set-write-community private<br>';





		return $configuration;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	* Store the running configuration for further deltas
	*/
	function store_running($running_conf)
	{
		$this->running_conf = $running_conf;
		return SMS_OK;
	}

	function provisioning()
	{
		return $this->update_conf();
	}

	// ------------------------------------------------------------------------------------------------
	/**
	*
	*/
	/*function build_conf()
	{
	    $generated_configuration = $this->generate_begin_conf();
	    $generated_configuration .= $this->generate();
	    $generated_configuration .= $this->generate_end_conf();
	    return $generated_configuration;
	}*/

	// function to be called after the configuration transfer
	function copy_to_running($conn, $cmd)
	{
		global $sdid;
		//global $sms_sd_ctx;
		//global $sendexpect_result;

		$tab[0] = $conn->getPrompt();
		$tab[1] = '[no]:';
		$tab[2] = ']?';
		$tab[3] = '[confirm]';
		$tab[4] = '[yes/no]';
		$tab[5] = '#'; // during provisionning prompt can change
    $index = 1;
    $result = '';
    for ($i = 1; ($i <= 10) && ($index !== 0); $i++)
    {
      $conn->send(__LINE__, $cmd);
      $index = $conn->expect(__LINE__, $tab, 500000);
      $result .= $sendexpect_result;
      switch ($index)
      {
        case 1:
          if (strpos($sendexpect_result, 'Dynamic mapping in use') !== false)
          {
            $cmd = "yes";
          } elseif(strpos($sendexpect_result, 'Saving this config to nvram') !== false) {
            #% Warning: Saving this config to nvram may corrupt any network management or security files stored at the end of nvram.
            #encounter during restore on a device....
            $cmd = "yes";
          }
          else
          {
            save_result_file($result, "conf.error");
            sms_log_error("$sdid:".__FILE__.':'.__LINE__.": [[!!! $sendexpect_result !!!]]\n");
            $conn->send(__LINE__, "\n");
            throw new SmsException("copy configuration to running error: " . $sendexpect_result, ERR_SD_CMDFAILED);
          }
          break;
        case 2:
          $cmd = '';
          break;
        case 3:
          $conn->send(__LINE__, "\n");
          $cmd = '';
          break;
        case 4:
          $cmd = 'yes';
          break;
        case 5:
          $connection->do_store_prompt();
          $index = 0;
          break;
        default:
          $index = 0;
        break;
      }
    } // loop while the router is asking questions

    return $result;
  }



  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function update_conf()
  {
    $generated_configuration = $this->get_generated_conf();
    $ret = oneaccess_lbb_apply_conf($generated_configuration);
    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */

  function get_current_firmware_name()
  {
  	global $sms_sd_ctx;

  	// Grab current firmware file name
  	$line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version | include version");
  	$current_firmware = substr($line, strpos($line, ':') + 1);
  	$current_firmware = trim($current_firmware);

  	return $current_firmware;
  }

  function update_firmware($param = '')
  {
  	global $sms_sd_ctx;
  	global $status_message;
  	global $disk_names;
  	//   	$dst_disk = "flash";

  	 status_progress('Checking firmware file', 'FIRMWARE');
  	if (strpos($param, "FILE=") !== false)
  	{
  		if (preg_match("/FILE=(?<filename>.*)$/", $param, $matches) > 0)
  		{
  			$firmware_file = $matches['filename'];
  		}
  		else
  		{
  			return ERR_NO_FIRMWARE;
  		}
  	}
  	else // use the repository
  	{
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
  	}
  	if (empty($firmware_file))
  	{
  		sms_log_info(__FUNCTION__ . ": no file specified.\n");
  		global $status_message;
  		$status_message = "No file specified";
  		return SMS_OK;
  	}

  	clearstatcache(TRUE, $firmware_file);
  	if (!file_exists($firmware_file))
  	{
  		return ERR_NO_FIRMWARE;
  	}

  	//   	foreach ($disk_names as $disk_name)
  		//   	{
  		//   		if (preg_match($disk_name, $firmware_file, $match) > 0)
  			//   		{
  			//   			$dst_disk = $match[0];
  			//   			break;
  			//   		}
  			//   	}

  	status_progress('Checking installed firmware', 'FIRMWARE');
  	// Check if the firmware is currently installed (flash:...)
  	$previous_firmware_name = $this->get_current_firmware_name();
  	$new_firmware_name = basename($firmware_file);
  	//$new_firmware_name = substr($new_firmware_name,0,strpos($new_firmware_name, '.'));


  	if ($new_firmware_name == $previous_firmware_name)
  	{
  		$status_message = 'Firmware already installed';
  		return SMS_OK;
  	}


  	status_progress('Checking available space on the router', 'FIRMWARE');
  	// Get file size
  	$firmware_size = filesize($firmware_file);

  	// Check size
  	$line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show device status flash | include free space");
  	if ($line !== false)
  	{
  		if (preg_match('/free\sspace\son\svolume:\s*(?<mem>.*)\sbytes/', $line, $matches) > 0)
  		{
  			$flash_avail = $matches['mem'];
  		}
  	}
  	$flash_avail = str_replace(',', "", $flash_avail);

  	if (empty($flash_avail) || $flash_avail < $firmware_size)
  	{
  		// not enough memory on device
  		return ERR_SD_ENOMEM;
  	}

  	status_progress('Transfering firmware file', 'FIRMWARE');
  	// Transfer firmware
  	$src = $firmware_file;
  	$dst = basename($firmware_file);
  	$sd_node_ip_addr = $this->sd->SD_NODE_IP_ADDR;

  	if($this->sd->SD_CONF_ISIPV6)
  	{
  		$sd_node_ip_addr = $_SERVER['SMS_ADDRESS_IPV6'];
  	}

  	$ret = send_file_to_router($src, "");
  	if ($ret !== SMS_OK)
  	{
  		return $ret;
  	}


  	status_progress('Verifying transfered file', 'FIRMWARE');

  	$line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "verify soft-file /$dst", "lire dans sdctx", 150000);
  	if (strpos($line, 'file is OK') === false)
  	{
  		return ERR_FIRMWARE_CORRUPTED;
  	}


  	status_progress('Configuring boot file', 'FIRMWARE');

  	// Configure new firmware
  	$line = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "mv /$dst /BSA/binaries/NewOs", ">");
  	if(strpos($line, 'disk full') !== false){
  		return ERR_SD_ENOMEM;
  	}
  	sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "rm /$dst", ">");
  	sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "mv /BSA/binaries/NewOs /BSA/binaries/OneOs", ">");

  	if ($ret !== SMS_OK)
  	{
  		return $ret;
  	}

  	// reboot
  	status_progress('Reloading device', 'FIRMWARE');

  	func_reboot();
  	//oneaccess_lbb_disconnect();
  	sleep(70);
  	// From here, we loose connection !
  	echo "Waiting for device up ...\n";
  	$ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
  	if ($ret != SMS_OK)
  	{
  		return $ret;
  	}
  	status_progress('Connecting to the device', 'FIRMWARE');

  	$loop = 5;
  	while ($loop > 0)
  	{
  		sleep(10); // wait for ssh to come up
  		$ret = oneaccess_lbb_connect();
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

  	// Check that the device booted on the new firmware
  	$current_firmware_name = $this->get_current_firmware_name();
  	if ($current_firmware_name !== $new_firmware_name)
  	{
  		return ERR_FIRMWARE_CORRUPTED;
  	}


  	return SMS_OK;
  }


}

/**
 * @}
 */

?>
