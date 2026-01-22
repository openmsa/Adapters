<?php
/*
 * Version: $Id: do_get_archive_conf.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Jun 18, 2010
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 *  $target_file   the target file on MSA
 */

// Verb JSGETARCHIVECONF

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'connect_cli.php');
require_once load_once('stormshield', 'nsrpc.php');

echo "Retrieving backup archive\n";

$thread_id = $_SERVER['THREAD_ID'];

// backup file on the ME
$local_backup = "/tmp/{$sdid}_{$thread_id}_conf.na";

$date = date('c');
$SMS_RETURN_BUF = "{$date}";

global $sms_sd_ctx;

// Generate the backup
connect();
$ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "config backup list=\"all\" > $local_backup");
if (is_error($ret))
{
  $err = __FILE__ . ':' . __LINE__ . ": Error config backup list=\"all\" > $local_backup returns\n$ret";
  sms_log_error("$err\n");
  $SMS_OUTPUT_BUF = $err;
  return ERR_SD_CMDFAILED;
}

disconnect();

// At the stage the backup file is generated on the ME
// copy it on MSA

if (file_exists($target_file))
{
  unlink($target_file);
}

$network = get_network_profile();
$sd = &$network->SD;

echo "/opt/sms/bin/sms_scp_transfer -r -s $local_backup -d $target_file -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT\n";

$ret_scp = exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/sms_scp_transfer -r -s $local_backup -d $target_file -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT", $output);

// remove the backup on the ME
connect();
sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", '>');
sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "rm $local_backup", '>');
disconnect();

if (!file_exists($target_file))
{
  $err = __FILE__ . ':' . __LINE__ . ": Impossible to copy backup from $local_backup to $target_file\n";
  sms_log_error("$err\n");
  $SMS_OUTPUT_BUF = $err;
  return ERR_SD_CMDFAILED;
}

$SMS_RETURN_BUF = "{$date}: Backup saved in {$target_file}";

return SMS_OK;

?>
?>