<?php

require_once 'smsd/sms_common.php';
require_once load_once('cisco_ftd_rest', 'me_connect.php');

require_once "$db_objects";

class MeRestoreConfiguration
{
  var $sdid;                // ID of the SD to update
  var $runningconf_to_restore;             //running conf retrieved from SVN /
  var $revision_id;         // revision id to restore
  var $job_id;

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  */
  function __construct($sdid)
  {
    $this->sdid = $sdid;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */

  function get_revision($revision_id)
  {
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd="/opt/sms/script/get_saved_conf --get {$this->sdid} r{$this->revision_id}";

    $ret = exec_local(__FILE__ . ':' . __LINE__,  $get_saved_conf_cmd, $output_array);
	if ($ret !== SMS_OK) {
      $msg = "Configuration with revision id {$this->revision_id} not found";
      throw new SmsException($msg, ERR_RESTORE_FAILED, __FILE__ . ':' . __LINE__);
    }

	$index = count($output_array);
	while($index)
	{
	  --$index;
	  if (strpos($output_array[$index], 'SMS_OK') !== false)
	  {
	    array_splice($output_array, $index, 1);
        break;
	  }
	}

	$this->runningconf_to_restore = array_to_string($output_array);

	return SMS_OK;
  }

  function restore_conf()
  {
    global $sms_sd_ctx;

    $work_dir = '/opt/sms/spool/tmp';
    // File name extension must be .txt
    $file_name = "$work_dir/import-config-{$this->sdid}.txt";

    $ret = file_put_contents($file_name, $this->runningconf_to_restore);
    if ($ret === false)
    {
      $msg = "Cannot create file $file_name (restore revision: {$this->revision_id})";
      throw new SmsException($msg, ERR_LOCAL_CMD, __FILE__ . ':' . __LINE__);
    }

    // Upload the configuration file to the ME
    $cmd = 'POST#/api/fdm/latest/action/uploadconfigfile';
    $additional_params = "-F 'fileToUpload=@{$file_name}'";
    $sms_sd_ctx->set_custom_headers('');
    $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd, $additional_params);
    $sms_sd_ctx->reset_custom_headers('');
    $disk_filename = $sms_sd_ctx->get_array_response()['diskFileName'];

    // Remove uploaded file
    unlink($file_name);

    // Import job
    $data = array (
        'diskFileName' => $disk_filename,
        'preserveConfigFile' => false,
        'autoDeploy' => true,
        'allowPendingChange' => false,
        'jobName' => 'MSA config import',
        'type' => 'scheduleconfigimport'
    );
    $data = json_encode ( $data );
    $cmd = "POST#/api/fdm/latest/action/configimport#{$data}";
    $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
    $this->job_id = $sms_sd_ctx->get_array_response()['jobHistoryUuid'];
  }

  public function finalize_restore()
  {
    global $sms_sd_ctx;

    // Check import job status
    $cmd = "GET#/api/fdm/latest/jobs/configimportstatus/{$this->job_id}";
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

    $ret = save_result_file($this->runningconf_to_restore, 'conf.applied');
    if ($ret !== SMS_OK)
    {
      $msg = "__FILE__ . ':' . __LINE__: Cannot save file conf.applied";
      throw new SmsException($msg, ERR_LOCAL_CMD,  __FILE__ . ':' . __LINE__);
    }
  }
}

?>