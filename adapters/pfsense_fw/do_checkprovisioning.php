<?php
/*
 * Version: $Id: do_checkprovisioning.php 22221 2009-09-30 12:46:20Z tmt $
 * Created: Nov 02, 2009
 * Available global variables
 *  $sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 */

// Verb CHECKPROVISIONING
require_once 'smsd/sms_common.php';
require_once "$db_objects";

require_once load_once('pfsense_fw', 'provisioning_stages.php');

// Check if the License is Attached to the device
$licence_file = "";
$ret = get_repo_files_map($map_conf, $error, 'License');

if (!empty($map_conf))
{
  foreach ($map_conf as $mkey => $file)
  {
    if (!empty($file))
    {
      $licence_file = $_SERVER['FMC_REPOSITORY'] . "/$file";
      break; // use this first file found
    }
  }
}

if ($licence_file == "")
{
  $provisioning_stages = $provisioning_stages_wo_license;
}
else
{
  $provisioning_stages = $provisioning_stages_with_license;
}

return require_once 'smsd/do_checkprovisioning.php';

?>
