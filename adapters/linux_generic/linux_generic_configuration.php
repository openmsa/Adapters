<?php
/*
 * Version: $Id: linux_generic_configuration.php 58927 2012-06-11 15:15:18Z abr $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once 'smsd/expect.php';

require_once load_once('linux_generic', 'common.php');
require_once load_once('linux_generic', 'adaptor.php');
require_once load_once('linux_generic', 'linux_generic_apply_conf.php');
require_once "$db_objects";
class linux_generic_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $profile_list; // List of managed profiles
  var $previous_conf_list; // Previous generated configuration loaded from files
  var $conf_list; // Current generated configuration waiting to be saved
  var $addon_list; // List of managed addon cards
  var $fmc_repo; // repository path without trailing /
  var $sd;

  // ------------------------------------------------------------------------------------------------
  /**
   * Constructor
   */
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->conf_pflid = 0;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $net = get_network_profile();
    $this->sd = &$net->SD;
  }

  // ------------------------------------------------------------------------------------------------
  /**
   * Get running configuration from the router
   */
  function get_running_conf()
  {
    global $sms_sd_ctx;
    global $sendexpect_result;

    // Run the CLI Cmd
    exec_local(__FILE__ . ':' . __LINE__, "/opt/sms/bin/sms -e JSCALLCOMMAND -i '".$this->sdid." IMPORT 0' -c '{}'", $output_array);
    $result = "";
    foreach ($output_array as $line)
    {
      if (strpos($line, "SMS_OK") === false)
      {
        $result .= $line;
      }
    }
    $js = json_decode($result, true);
    if (!empty($js['sms_result']))
    {
      // PHP 5.4.0
      //$SMS_OUTPUT_BUF = json_encode($js->sms_result, JSON_PRETTY_PRINT);
      ob_start();
      print_r($js['sms_result']);
      $SMS_OUTPUT_BUF = ob_get_contents();
      ob_end_clean();
      $this->running_conf = trim($SMS_OUTPUT_BUF);
    }
    else
    {
      $this->running_conf = '';
    }
    return $this->running_conf;
  }



  // ------------------------------------------------------------------------------------------------
  /**
   * Generate the general pre-configuration
   * @param $configuration   configuration buffer to fill
   */
  function generate_pre_conf(&$configuration)
  {
    //$configuration .= "!PRE CONFIG\n";
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
    //$configuration .= "! CONFIGURATION GOES HERE\n";
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
    //$configuration .= "!POST CONFIG\n";
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
    return SMS_OK;
  }
  // ------------------------------------------------------------------------------------------------
  /**
   *
   */
  function build_conf(&$generated_configuration)
  {
    //$this->monitoring_conf($generated_configuration);
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

    if (!empty($generated_configuration))
    {
      $ret = linux_generic_apply_conf($generated_configuration, $this->is_ztd);
    }
    return $ret;
  }
  function provisioning()
  {
    return $this->update_conf();
  }
  function get_staging_conf()
  {
    $staging_conf = PATTERNIZETEMPLATE("staging_conf.tpl");
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $staging_conf, 'STAGING_CONFIG', 'Configuration');
    return $staging_conf;
  }
  function monitoring_conf(&$generated_configuration)
  {
    if ($this->sd->SD_LOG)
    {

      $generated_configuration .= PATTERNIZETEMPLATE('snmp_conf.tpl');
    }
    if ($this->sd->SD_LOG_MORE)
    {
      $generated_configuration .= PATTERNIZETEMPLATE('syslog_conf.tpl');
    }

    return SMS_OK;
  }
  function reboot($event)
  {
    status_progress('Reloading device', $event);

    func_reboot();
    sleep(40);
    $ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
    if ($ret != SMS_OK)
    {
      return $ret;
    }
    status_progress('Connecting to the device', $event);

    $loop = 5;
    while ($loop > 0)
    {
      sleep(10); // wait for ssh to come up
      $ret = linux_generic_connect();
      if ($ret == SMS_OK)
      {
        break;
      }
      $loop--;
    }

    return $ret;
  }

  function get_data_files($event, $src_dir, $file_pattern, $dst_dir)
  {
    global $sms_sd_ctx;
    global $status_message;

    $ret = SMS_OK;
    $repo_dir = $_SERVER['FMC_REPOSITORY'].'/Datafiles';

    status_progress('Reading files on device', $event);

    $file_list = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "dir -1 {$src_dir}");
    $patterns = array();
    $patterns[0] = '@\.@';
    $patterns[1] = '@\*@';
    $patterns[2] = '@\?@';
    $replacements = array();
    $replacements[0] = '\.';
    $replacements[1] = '\S*';
    $replacements[2] = '.?';
    $pattern = preg_replace($patterns, $replacements, $file_pattern);
    $pattern = "@^(?<file>{$pattern})\s*$@m";
    echo "PATTERN [$pattern]\n";
    #2021/07/12:15:51:33:(D):smsd:RAB133:JSACMD:: PATTERN [@^ .* (?<file>test\.txt)\s*$@m]

    if (preg_match_all($pattern, $file_list, $matches) > 0)
    {
      foreach ($matches['file'] as $file_line)
      {
        status_progress("{$status_message}Transfering file {$src_dir}{$file_line} to {$repo_dir}/{$dst_dir}/{$file_line}", $event);
        try
        {
          scp_from_router("{$src_dir}{$file_line}", "{$repo_dir}/{$dst_dir}/{$file_line}");
          // Check file size
          check_file_size("{$repo_dir}/{$dst_dir}/{$file_line}", $file_line, false, str_replace(':', '', $src_dir));
          $status_message .= "{$src_dir}{$file_line} OK\n | ";
          // create the .meta file
          $tmp = preg_split("@/@", $dst_dir);
          $repo = $tmp[0];
          $gtod = gettimeofday();
          $date_modif = floor($gtod['sec'] * 1000 + $gtod['usec'] / 1000);
          $meta_file = "{$repo_dir}/{$dst_dir}/.meta_{$file_line}";
          $meta_content = <<< EOF
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<metadata>
    <map>
        <entry>
            <key>FILE_TYPE</key>
            <value>binary</value>
        </entry>
        <entry>
            <key>DATE_MODIFICATION</key>
            <value>{$date_modif}</value>
        </entry>
        <entry>
            <key>COMMENT</key>
            <value>Uploaded from {$this->sdid}</value>
        </entry>
        <entry>
            <key>REPOSITORY</key>
            <value>{$repo}</value>
        </entry>
        <entry>
            <key>DATE_CREATION</key>
            <value>{$date_modif}</value>
        </entry>
        <entry>
            <key>CONFIGURATION_FILTER</key>
            <value></value>
        </entry>
        <entry>
            <key>TYPE</key>
            <value>UPLOAD</value>
        </entry>
        <entry>
            <key>TAG</key>
            <value>{$src_dir}{$file_line}</value>
        </entry>
    </map>
</metadata>
EOF;
          file_put_contents($meta_file, $meta_content);
        }
        catch (SmsException $e)
        {
          unlink("{$repo_dir}/{$dst_dir}/{$file_line}");
          $ret = $e->getCode();
          $status_message .= $e->getMessage();
          $status_message .= "\n | ";
          if ($sms_sd_ctx === null)
          {
            // connection lost, try a last time
            $res = cisco_isr_connect();
            if ($res !== SMS_OK)
            {
              // give up
              $status_message .= "Connection lost with the device, stopping the transfer";
              return $ret;
            }
          }
        }
      }
    }
    return $ret;
  }

}

?>
