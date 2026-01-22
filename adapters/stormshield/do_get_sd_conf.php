<?php

/*
 * Version: $Id: do_get_sd_conf.php 23900 2009-11-19 13:40:40Z tmt $
* Created: Jun 18, 2008
* Available global variables
* 	$sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info   sd_info structure
*  $sdid
*  $sms_module    module name (for patterns)
*  $SMS_RETURN_BUF     string buffer containing the result
*/

// Get router configuration, archive format

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'connect_cli.php');
require_once load_once('stormshield', 'nsrpc.php');

echo "Retrieving backup archive\n";

$thread_id = $_SERVER['THREAD_ID'];

// backup file on the ME
$local_backup = "/tmp/{$sdid}_{$thread_id}_conf.na";

// temporary backup file on MSA
$temp_backup = "/opt/sms/spool/tmp/{$sdid}_{$thread_id}_conf.na";

// target backup file on MSA
$target = "/opt/sms/spool/routerconfigs/{$sdid}/conf.na";

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

// At this stage the backup file is generated on the ME
// copy it on MSA

if (file_exists($temp_backup))
{
  unlink($temp_backup);
}

$network = get_network_profile();
$sd = &$network->SD;

echo "/opt/sms/bin/sms_scp_transfer -r -s $local_backup -d $temp_backup -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT\n";

$ret_scp = exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/sms_scp_transfer -r -s $local_backup -d $temp_backup -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT", $output);

// remove the backup on the ME
connect();
sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", '>');
sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "rm $local_backup", '>');
disconnect();

if (!file_exists($temp_backup))
{
  $err = __FILE__ . ':' . __LINE__ . ": Impossible to copy backup from $local_backup to $temp_backup\n";
  sms_log_error("$err\n");
  $SMS_OUTPUT_BUF = $err;
  return ERR_SD_CMDFAILED;
}

rename($temp_backup, $target);

$SMS_RETURN_BUF = "{$date}: Backup saved in {$target}";

return SMS_OK;
