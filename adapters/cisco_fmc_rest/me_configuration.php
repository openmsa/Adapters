<?php
require_once 'smsd/sms_common.php';

require_once load_once('cisco_fmc_rest', 'adaptor.php');
require_once load_once('cisco_fmc_rest', 'me_apply_conf.php');

require_once "$db_objects";

class MeConfiguration
{
  var $sdid; // ID of the SD to update

  /**
	* Constructor
	*/
  function __construct($sdid)
  {
    $this->sdid = $sdid;
  }

  protected function extract_running_conf($zipped_archive, $dest_dir)
  {
    // The archive is supposed to have only one file called full_config.txt
    // unzip
    $file_to_extract = 'full_config.txt';
    $zip_cmd = "unzip -o $zipped_archive $file_to_extract -d $dest_dir";
    $ret = exec_local (__FILE__ . ':' . __LINE__, $zip_cmd, $output_array );
    if ($ret !== SMS_OK) {
      $out_str = implode("\n", $output_array);
      $msg = "__FILE__ . ':' . __LINE__: Command $zip_cmd Failed, $out_str";
      throw new SmsException($msg, $ret, __FILE__ . ':' . __LINE__);
    }

    // read the config
    $running_conf_file = "$dest_dir/$file_to_extract";
    $running_conf = file_get_contents($running_conf_file);
    if ($running_conf === false)
    {
      $msg = "Failed to to read $running_conf_file";
      throw new SmsException($msg, ERR_LOCAL_CMD, __FILE__ . ':' . __LINE__);
    }

    // remove files
    unlink($zipped_archive);
    unlink($running_conf_file);

    return $running_conf;
  }

  /**
	* Get running configuration from the router
	* Actually an export of the configuration
	*/
  function get_running_conf()
  {
    global $sms_sd_ctx;

    // Export job
    $data = array(
        'diskFileName' => "export-config-{$this->sdid}",
        'doNotEncrypt' => true,
        'configExportType' => 'FULL_EXPORT',
        'jobName' => 'MSA config export',
        'type' => 'scheduleconfigexport'
    );
    $data = json_encode ( $data );
    $cmd = "POST#/api/fdm/latest/action/configexport#{$data}";
    $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
    $job_id = $sms_sd_ctx->get_array_response()['jobHistoryUuid'];

    // Check export job status
    $cmd = "GET#/api/fdm/latest/jobs/configexportstatus/{$job_id}";
    $waiting_time = 1; // seconds
    $timeout = 120; // seconds
    do
    {
      sleep($waiting_time);
      $timeout -= $waiting_time;
      $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
      $state = $sms_sd_ctx->get_array_response()['status'];
    }
    while (($state == 'IN_PROGRESS' || $state == 'QUEUED') && $timeout > 0);

    if ($state != 'SUCCESS')
    {
      if ($state == 'IN_PROGRESS' || $state == 'QUEUED')
      {
        $msg = 'Config export timeout';
        $err = ERR_SD_CMDTMOUT;
      }
      else
      {
        $msg = "Config export failed (state = $state)";
        $err = ERR_SD_FAILED;
      }
      throw new SmsException($msg, $err, __FILE__ . ':' . __LINE__);
    }
    $disk_filename = $sms_sd_ctx->get_array_response()['diskFileName'];

    // Download the configuration file
    $cmd = "GET#/api/fdm/latest/action/downloadconfigfile/{$disk_filename}";
    $work_dir = '/opt/sms/spool/tmp';
    $downloaded_file = "{$work_dir}/{$disk_filename}";
    $additional_params = "> $downloaded_file";
    $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd, $additional_params);

    // the file uploaded is a zipped file, unzip it
    $running_conf = $this->extract_running_conf($downloaded_file, $work_dir);

    // Remove exported file on the ME
    $cmd = "DELETE#/api/fdm/latest/action/configfiles/{$disk_filename}";
    $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);

    return $running_conf;
  }

  /**
   *
   * @param string $param
   * @return string
   */
  function update_firmware($param = '')
  {
    return ERR_SD_NOT_SUPPORTED;
  }

  /**
	 * Mise a jour de la licence
	 * Attente du reboot de l'equipement
	 */
  function update_license()
  {
  	return ERR_SD_NOT_SUPPORTED;
  }
}

?>