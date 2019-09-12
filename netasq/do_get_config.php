<?php


/*
 * Version: $Id: do_get_config.php 22221 2009-09-30 12:46:20Z tmt $
* Created: Jul 31, 2008
* Available global variables
* 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info        sd_info structure
*  $sms_csp            pointer to csp context to send response to user
*  $sdid
*  $sms_module         module name (for patterns)
*/

// Get generated configuration for the router
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_configuration.php');

$conf = new netasq_configuration($sdid);

$ROOT = "/opt/sms/spool/fmc/tmp_$sdid";

$conf->init_get_config($ROOT);

$ret = $conf->build_conf(false);
if ($ret !== SMS_OK)
{
  return $ret;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);

$generated_configuration = "\n<configuration>\n";

parse_dir($ROOT, 'get_conf_files');

$generated_configuration .= "\n</configuration>\n";

sms_send_user_ok($sms_csp, $sdid, $generated_configuration);

return SMS_OK;

function get_conf_files($dir, $file, $local_path)
{
  global $generated_configuration;
  global $finfo;

  if (is_dir("$dir/$file"))
  {
    parse_dir("$dir/$file", 'get_conf_files', "$local_path/$file");
    return;
  }

  $file_name = "$dir/$file";

  // filter binary file
  $ftype = $finfo->file($file_name);
  if (($ftype !== false) && (strpos($ftype, 'text') !== false))
  {
    $conf_file = file_get_contents($file_name);
    if ($conf_file === false)
    {
      sms_log_error(__FILE__ . ":" . __LINE__ . ": file_get_contents(\"$file_name\") failed\n");
      $conf_file = 'Error in reading file';
    }
  }
  else
  {
    $conf_file = "Binary file\n";
  }

  $generated_configuration .= "\n\n<section name=\"$local_path/$file\">\n";
  $generated_configuration .= $conf_file;
  $generated_configuration .= "\n</section>\n";
}
?>