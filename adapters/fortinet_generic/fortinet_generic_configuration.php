<?php

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'common.php');
require_once load_once('fortinet_generic', 'adaptor.php');
require_once load_once('fortinet_generic', 'fortinet_generic_apply_conf.php');

require_once "$db_objects";


class fortinet_generic_configuration
{
        var $conf_path;           // Path for previous stored configuration files
        var $sdid;                // ID of the SD to update
        var $running_conf;        // Current configuration of the router
        var $conf_to_restore;     // configuration to restore
        var $profile_list;        // List of managed profiles
        var $fmc_repo;            // repository path without trailing /
        var $sd;

        // ------------------------------------------------------------------------------------------------
        /**
        * Constructor
        */
        function __construct($sdid, $is_provisionning = false)
        {
                $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
                $this->sdid = $sdid;
                $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
                $net = get_network_profile();
                $this->sd = &$net->SD;
                $this->conf_pflid = $this->sd->SD_CONFIGURATION_PFLID;
                $this->set_additional_vars();
        }

        // ------------------------------------------------------------------------------------------------
        /**
        * Get running configuration from the router
        */
        function get_running_conf()
        {
                global $sms_sd_ctx;

    $tab[0] = "$";
    $tab[1] = "#";
    $tab[2] = "(global) #";
    $tab[3] = "(global) $";
    $tab[4] = "(vdom) $";
    $tab[5] = "(vdom) $";
  
        $temp_buffer=sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system status');
        if(strpos($temp_buffer, 'Virtual domain configuration: enable') !== false){
          //If VDOM is enabled get out of vdom and go into config global to take config backup
          $temp_buffer = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $tab);
          $temp_buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'config global', $tab, 40000);
          $msa_ip = $_SERVER['SMS_ADDRESS_IP'];
          $dev_id=$this->sd->SDID;
          $tmp_conf_file="$dev_id"."_running.conf";
          $cmd="execute backup config tftp $tmp_conf_file $msa_ip";
          $temp_buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd, $tab,4000);
        	if(strpos($temp_buffer, 'Send config file to tftp server OK') !== false){
        	    $backup_file_path="/opt/sms/spool/tftp/"."$tmp_conf_file";
        		$running_conf = file_get_contents ($backup_file_path);
        		//remove all text between "config vpn certificate local" and "end" including these two lines
        		$list= explode("config vpn certificate local",$running_conf);
        		$remaining_conf= strstr($list[1],"end");
        		$remaining_conf = substr($remaining_conf,3);
        		$running_conf="$list[0]$remaining_conf";
        		//config backedup, go back to root vdom
        		$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $tab,4000);
        		$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'config vdom', $tab, 40000);
        		$cmd = "edit root";
        		$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd, $tab, 40000);
        		$tmp_file_cleanup_cmd = "rm -f $backup_file_path";
        		shell_exec($tmp_file_cleanup_cmd);
        		}else{
        		   throw new SmsException("", ERR_SD_CMDTMOUT);
        		}

        		$IS_VDOM_ENABLED=true;

        }else{

                $running_conf = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'show');

                }
                if (!empty($running_conf))
                {
                  $running_conf = remove_line_starting_with($running_conf, '#conf_file_ver=');
                  $running_conf = trim($running_conf);
                }

                $this->running_conf = $running_conf;
                return $this->running_conf;
        }

        // ------------------------------------------------------------------------------------------------
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

                $res = array_to_string($output);

                // remove useless lines
                $patterns = array ();
                $patterns [0] = "/OK\s*/";
                $patterns [1] = "/SMS_\s*/";
                $replacements = array ();
                $replacements [0] = "";
                $replacements [1] = "";

                $this->conf_to_restore = preg_replace($patterns, $replacements, $res);

                return SMS_OK;
        }

	//------------------------------------------------------------------------------------------------
	function restore_conf()
	{
		global $sms_sd_ctx;
    $tab[0] = "$";
    $tab[1] = "#";

                //$this->conf_to_restore
                $filename = "{$_SERVER['TFTP_BASE']}/{$this->sdid}.cfg";
                file_put_contents($filename, $this->conf_to_restore);

                $IS_VDOM_ENABLED = false;
        $temp_buffer=sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system status');
        if(strpos($temp_buffer, 'Virtual domain configuration: enable') !== false){

           //If VDOM is enabled get out of vdom and go into config global to take config backup
           $temp_buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $tab);
           $temp_buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'config global', '(global)', 40000);
           $IS_VDOM_ENABLED=true;
        }


                sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "execute restore config tftp {$this->sdid}.cfg {$_SERVER['SMS_ADDRESS_IP']}", "(y/n)");
                unset($tab);
                $tab[0] = "File check OK.";
                $tab[1] = "Invalid config file";
                $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "y", $tab);
                if ($index !== 0)
                {
                        $SMS_OUTPUT_BUF = $sendexpect_result;
                        return ERR_RESTORE_FAILED;
                }
                unlink($filename);

                return SMS_OK;
        }

        // ------------------------------------------------------------------------------------------------
        /**
        * Generate the general pre-configuration
        * @param string $configuration   configuration buffer to fill
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
        * @param string $configuration   configuration buffer to fill
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
        function update_conf()
        {
                $ret = $this->build_conf($generated_configuration);

                if(!empty($generated_configuration))
                {
                        $ret = fortinet_generic_apply_conf($generated_configuration);
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

        function wait_until_device_is_up()
        {
          return wait_for_device_up ($this->sd->SD_IP_CONFIG, 60, 300);
        }

        function set_additional_vars()
        {
                if (!empty($this->sd->SD_CONFIGVAR_list['DCGROUP']))
                {
                        $dc_group = $this->sd->SD_CONFIGVAR_list['DCGROUP']->VAR_VALUE; // Name of the datacenter
                        //$node = $this->sd->SD_NODE_NAME; // Name of the node
                        $datacenter_mapping_file = $_SERVER['FMC_REPOSITORY'] . '/Datafiles/DataCenterMapping/mapping.ini';
                        if (file_exists($datacenter_mapping_file))
                        {
                                $data_center_mapping = parse_ini_file($datacenter_mapping_file, true);
                                if ($data_center_mapping === false || empty($data_center_mapping[$dc_group]))
                                {
                                        $data_center_ip = $this->sd->SD_NODE_IP_ADDR;
                                }
                                else
                                {
                                        // $data_center_ip = $data_center_mapping[$dc_group][$node];
				        $data_center_ip = $data_center_mapping[$dc_group][$dc_group];
                                }
                        }
                        else
                        {
                                $data_center_ip = $this->sd->SD_NODE_IP_ADDR;
                        }
                }
                else
                {
                        $data_center_ip = $this->sd->SD_NODE_IP_ADDR;
                }
                $this->additional_vars = array();
                $this->additional_vars['DATACENTER_IP'] = $data_center_ip;
                echo "data_center_ip: $data_center_ip\n";
        }

        function get_additional_vars($key = null)
        {
                if (!empty($key))
                {
                        return $this->additional_vars[$key];
                }
                return $this->additional_vars;
        }

  function get_current_firmware_version()
  {
    global $sms_sd_ctx;

    $temp_buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system status', "Version:", 5000);
    //$temp_buffer = Version: FortiGate-VM64 v5.2.11,build0754,170421 (GA)
    if (preg_match("/,(build\d+)/",$temp_buffer,$match)){
      $current_firmware = $match[1];
    }else{
      $current_firmware = $temp_buffer;
    }

    return $current_firmware;
  }

  function finalize_firmware_upgrade(&$status_message, $status_type, $firmware_file, $initial_sec_to_wait=60)
  {
    status_progress("Wait at least $initial_sec_to_wait sec the device reboot", $status_type);
    $ret = wait_for_device_up($this->sd->SD_IP_CONFIG, 60, $initial_sec_to_wait);
    if ($ret != SMS_OK)
    {
      $status_message = "The device is no more reachable after reboot";
      sms_log_error(__FILE__ . ':' . __LINE__ . " : $status_message\n");
      return $ret;
    }

    status_progress('Connecting to the device', $status_type);
    $loop = 5;
    while ($loop > 0)
    {
      sleep(10); // wait for ssh to come up
      $ret = fortinet_generic_connect();
      if ($ret == SMS_OK)
      {
        break;
      }
      $loop--;
    }
    if ($ret != SMS_OK)
    {
      $status_message = "Cannot connect to the device after reboot";
      sms_log_error(__FILE__ . ':' . __LINE__ . " : $status_message\n");
      return $ret;
    }

    status_progress('Checking the firmware currently used', $status_type);

    // Check that the device booted on the new firmware version
    $new_firmware = $this->get_current_firmware_version(); //build0398
    //If OK the $new_firmware string should be inside the firmware file name
    if ((strpos($firmware_file, $new_firmware)) === false)
    {
      $status_message = "The device firmware version $new_firmware does not match the new firmware $firmware_file";
      sms_log_error(__FILE__ . ':' . __LINE__ . " : $status_message\n");
      return ERR_SD_FIRMWARE_NO_UPGRADES;
    }

    status_progress("UPDATE finished OK new_firmware=$new_firmware", $status_type);

    $status_message = "New firmware version is $new_firmware";

    return SMS_OK;
  }

  /**
   * Firmware upgrade/downgrade using CLI
   * @param string $status_message
   * @return string
   */
  function update_firmware(&$status_message, $status_type)
  {
    global $sms_sd_ctx;

        // IF firmware file OK : ....#####################################################################################
        // Get image from tftp server OK.
        // Connection to 10.30.18.185 closed.

    // IF firmware file NOK : ....#####################################################################################
        // Get image from tftp server OK.
        // Check image error.
        // Command fail. File is not an update file.

    if (!empty($this->sd->SD_CONFIGVAR_list['tftp_server_ip']->VAR_VALUE) && !empty($this->sd->SD_CONFIGVAR_list['tftp_server_firmware_path']->VAR_VALUE) && !empty($this->sd->SD_CONFIGVAR_list['tftp_server_firmware_filename']->VAR_VALUE))
    {
      $remote_tftp = true;
      $tftp_server_addr = $this->sd->SD_CONFIGVAR_list['tftp_server_ip']->VAR_VALUE;
      $tftp_dir_light = $this->sd->SD_CONFIGVAR_list['tftp_server_firmware_path']->VAR_VALUE;
      $firmware_file = $this->sd->SD_CONFIGVAR_list['tftp_server_firmware_filename']->VAR_VALUE;
    }
    else
    {
      // use the repository
      $remote_tftp = false;

      $ret = get_repo_files_map($map_conf, $error, 'Firmware');
      if ($ret !== SMS_OK)
      {
        // xml entity file broken
        $status_message = 'Error xml entity file broken';
        return $ret;
      }
      if (!empty($map_conf))
      {
        foreach ($map_conf as $mkey => $file)
        {
          if (!empty($file))
          {
            $fmc_repo_firmware_file = "{$this->fmc_repo}/$file";
            $firmware_file = basename($file);
            $tftp_server_addr = $_SERVER['SMS_ADDRESS_IP'];
            $tftp_dir_light = "Fortinet_firmware";
            $tftp_dir = "{$_SERVER['TFTP_BASE']}/$tftp_dir_light";  //  /opt/sms/spool/tftp/Fortinet_firmware
            $tftp_file =  $tftp_dir.'/'. $firmware_file;
            break; // use this first file found
          }
        }
      }
    }

    if (empty($firmware_file))
    {
      $status_message = 'No file specified';
      sms_log_error(__FILE__ . ':' . __LINE__ . ": $status_message\n");
      return ERR_NO_FIRMWARE;
    }

    if ($remote_tftp == false)
    {
      clearstatcache(TRUE, $fmc_repo_firmware_file);
      if (!file_exists($fmc_repo_firmware_file))
      {
        $status_message = "File does not exist $fmc_repo_firmware_file";
        sms_log_error(__FILE__ . ':' . __LINE__ . ": $status_message\n");
        return ERR_NO_FIRMWARE;
      }

      status_progress("Transfering firmware file to TFTP server", $status_type);

      // Copy the file to the TFTP server
      $do_copy = true;
      if(!is_dir($tftp_dir))
      {
         mkdir_recursive($tftp_dir, 0775);
      }
      else
      {
        //Check if the file already exist on the TFTP server
        if (file_exists($tftp_file))
        {
          //file already exist, check if it is necessary to change the file
          // may be an other upgrade is running, we should not change the file
          if(filesize($fmc_repo_firmware_file) === filesize($tftp_file))
          {
            if (md5_file($fmc_repo_firmware_file) === md5_file($tftp_file))
            {
              $do_copy = false;
              status_progress("firmware file already present on TFTP server $tftp_file, no copy", $status_type);
            }
          }
        }
      }

      if ($do_copy)
      {
        if (copy($fmc_repo_firmware_file, $tftp_file))
          {
            status_progress("firmware file transfered to TFTP server $tftp_file", $status_type);
          }
          else
          {
            status_progress("Can not copy the firmware file to TFTP server $tftp_file", $status_type);
          }
      }
    }

    //ex : $command= execute restore image tftp Fortinet_firmware/FWB_VM-64bit-v600-build0398-FORTINET.out 10.30.18.155
    $command = "execute restore image tftp $tftp_dir_light/$firmware_file $tftp_server_addr";
    status_progress("Will run on the device: $command", $status_type);
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $command);

    //Answer to This operation will replace the current firmware version!  Do you want to continue? (y/n)y
    status_progress("Checking image", $status_type);
    try
    {
      unset($tab);
      $tab[0] = "(y/n)";
      $tab[1] = "Command fail";
      $tab[2] = 'Transfer timed out';
      $tab[3] = 'File not found';
      $index =  0;
      $loop=0;
      while ($index == 0 && $loop++<5)
      {
         # if Image file uploaded is marked as a Feature image, are you sure you want to upgrade? need anwers Y 2 more times  and more time for UTM HA with 'Send image to HA secondary':
         //sms_log_error(__FILE__ . ':' . __LINE__ . " before wait loop=$loop\n");
         $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab, 600000);

         if($index === 0){
           $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, "y");  #not used sendCmd because we should not send the 'enter' after y
         }
      }
      //sms_log_error(__FILE__ . ':' . __LINE__ . " end all loop=$loop index=$index\n");

      //Check if we got an error, if the file is OK the device will reboot
      if ($index == 2)
      {
        return ERR_SD_CMDTMOUT;
      }
      else
      {
        return ERR_FIRMWARE_CORRUPTED;
      }
    }
    catch (SmsException | Error $e)
    {
      // This is the normal case, the device is rebooting
      $err = $e->getMessage();
      if (strpos($err, 'Command fail.') !== false)
      {
        // this should not happen but in case
        $status_message = "Firmware update error2: $err";
        sms_log_error(__FILE__ . ':' . __LINE__ . " : $status_message\n");
        return ERR_FIRMWARE_CORRUPTED;
      }
    }

    $ret = $this->finalize_firmware_upgrade($status_message, $status_type, $firmware_file);

    return $ret;
  }

  /**
   * Firmware upgrade/downgrade using REST API
   * @param string $status_message
   * @return string
   */
  function update_firmware_rest(&$status_message, $status_type)
  {
    // This run for fortiweb 6.x cf https://help.fortinet.com/fweb/621/api_html/
    // But it doesn't run on fortigate 5.x (the url /api/v1.0/System/Maintenance/FirmwareUpgradeDowngrad doesn't exist)

    $ret = get_repo_files_map($map_conf, $error, 'Firmware');
    if ($ret !== SMS_OK){
      // xml entity file broken
      $status_message = 'Error xml entity file broken';
      return $ret;
    }
    $fmc_repo_firmware_file = "";
    if (!empty($map_conf)){
      foreach ($map_conf as $mkey => $file){
        if (!empty($file)){
          $fmc_repo_firmware_file = "{$this->fmc_repo}/$file";
        }
      }
    }
    if (!file_exists($fmc_repo_firmware_file)){
      $status_message = "File does not exist $fmc_repo_firmware_file";
      sms_log_error(__FILE__ . ':' . __LINE__ . ": $status_message\n");
      return ERR_NO_FIRMWARE;
    }

    status_progress("Call firmware update rest api on device", $status_type);

    //curl -k -X POST -H "Content-Type: multipart/form-data" -H "Authorization:xxx" -F "imageFile=@/opt/fmc_repository/Firmware/A05/FORTINET/Fortiweb/FWB_VM-64bit-v600-build0398.out" "https://10.30.18.185:90/api/v1.0/System/Maintenance/FirmwareUpgradeDowngrade"
    $auth_details = base64_encode($this->sd->SD_LOGIN_ENTRY.":".$this->sd->SD_PASSWD_ENTRY);
    $url = "https://".$this->sd->SD_IP_CONFIG.":90/api/v1.0/System/Maintenance/FirmwareUpgradeDowngrade";

    $curl = curl_init($url);
    $curl_httpheader = array('Content-Type: multipart/form-data', "Authorization:$auth_details");
    curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_httpheader);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 1500);
    curl_setopt($curl, CURLOPT_NOSIGNAL, true);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $cfile = curl_file_create($fmc_repo_firmware_file);
    $data = array('imageFile' => $cfile);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    $result = curl_exec($curl);

    if($result === false) {
      $curl_errno = curl_errno($curl);
      $curl_error = curl_error($curl);
      $status_message = "Error for $curl_cmd: ($curl_errno): $curl_error";
      sms_log_error(__FILE__ . ':' . __LINE__ . " :  $status_message\n");
      curl_close($curl);
      return ERR_SD_CMDFAILED;
    }

    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($httpcode < 200 || $httpcode >= 300) {
      $response = json_decode($result, true);
      // OK       : $response = { "_id": "only" }
      // bad file : $response = { "msg": "File is not an update file.Try checking disk space and available memory first.", "status": 500 }
      $status_message = "Firmware update error: http code: $httpcode";
      if (isset($response['msg'])) {
        $status_message .= ", ".$response['msg'];
      }
      sms_log_error(__FILE__ . ':' . __LINE__ . " :  $status_message\n");
      return ERR_FIRMWARE_CORRUPTED;
    }

    $firmware_file = basename($fmc_repo_firmware_file);

    sms_sleep(20);

    $ret = $this->finalize_firmware_upgrade($status_message, $status_type, $firmware_file, 100);

    return $ret;
  }

}

?>

