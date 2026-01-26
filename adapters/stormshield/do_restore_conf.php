<?php
/*
 * Version: $Id: do_restore.php 39436 2011-02-10 10:57:34Z oda $
 * Created: Jun 30, 2008
 * Available global variables
 * 	$sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 * 	$SMS_RETURN_BUF    string buffer containing the result
 *
 *  $revision_id     SVN rev id for restore
 *  $sms_msg         message
 *  $config_type     type of configuration (CONF_FILE or CONF_BIN)
 */

// Restore configuration from archive file

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'connect_cli.php');
require_once load_once('stormshield', 'nsrpc.php');

function get_old_revision($restore_conf_file, $revision_id)
{
  global $sdid;

  echo("restore_from_old_revision revision_id: $revision_id\n");

  $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --getfile {$sdid} file {$restore_conf_file} r{$revision_id}";

  $ret = exec_local(__FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output);
  if ($ret !== SMS_OK)
  {
    echo("no running conf found\n");
    unlink($restore_conf_file);
    return $ret;
  }

  if (!file_exists($restore_conf_file))
  {
    echo("no running conf found\n");
    return ERR_CONFIG_EMPTY;
  }

  return SMS_OK;
}

function copy_restore_file_to_me($sd, $src, $dst)
{
  global $sms_sd_ctx;

  $ret = SMS_OK;

  echo "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT\n";

  exec_local(__FILE__.':'.__LINE__, "/opt/sms/bin/sms_scp_transfer -s $src -d $dst -a $sd->SD_IP_CONFIG -l $sd->SD_LOGIN_ENTRY -p '$sd->SD_PASSWD_ENTRY' -P $sd->SD_MANAGEMENT_PORT", $output);
  // no check ot the outcome, will check the presence of the file on the ME later on

  // remove the backup on the MSA
  unlink($src);

  // check file on ME
  connect();

  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", '>');
  $result = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "test -f $dst; echo $?", '>');
  if (strpos($result, "\n0") === false)
  {
    $err = __FILE__ . ':' . __LINE__ . ": Impossible to copy restore file from $src to $dst\n";
    sms_log_error("$err\n");
    $ret =  ERR_SD_CMDFAILED;
  }

  disconnect();

  return $ret;
}

function restore_conf($sd, $restore_conf_file)
{
  global $sms_sd_ctx;

  connect();

  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "modify on force");

  $ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "config restore list=\"all\" refresh=1 < $restore_conf_file");

  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "quit", '>');
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "rm $restore_conf_file", '>');

  if (is_error($ret))
  {
    $err = __FILE__ . ':' . __LINE__ . ": Error config restore list=\"all\" refresh=1 < $restore_conf_file returns\n$ret";
    sms_log_error("$err\n");
    disconnect();
    return ERR_SD_CMDFAILED;
  }

  // reboot
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'cli', 'assword');
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $sd->SD_PASSWD_ENTRY);
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "system reboot", '>');

  disconnect();

  return SMS_OK;
}

function wait_until_device_is_up($sd, $nb_loop = 60, $initial_sec_to_wait = 30)
{
  // wait the device become up after reboot
  $ret = wait_for_device_up($sd->SD_IP_CONFIG, $nb_loop, $initial_sec_to_wait);
  if ($ret != SMS_OK)
  {
    return $ret;
  }

  sms_sleep($initial_sec_to_wait); // Wait for the ssh service
  $done = $nb_loop;
  do
  {
    echo "waiting for the device (SSH), $done\n";
    sleep(5);
    try
    {
      connect();
      break;
    }
    catch (Exception | Error $e)
    {
      $done--;
    }
  } while ($done > 0);

  if ($done === 0)
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ": The device stay DOWN\n");
    return ERR_SD_CMDTMOUT;
  }

  disconnect();

  return SMS_OK;
}


$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Restoring revision $revision_id");

sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

// Asynchronous mode, the user socket is now closed, the results are written in database

$network = get_network_profile();
$sd = &$network->SD;

try
{
  $restore_conf_file = "{$sdid}_r{$revision_id}.conf";
  $msa_restore_conf_file = "/opt/sms/spool/tmp/{$restore_conf_file}";

  $ret = get_old_revision($msa_restore_conf_file, $revision_id);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Getting revision $revision_id failed");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  $me_restore_conf_file = "/tmp/{$restore_conf_file}";

  $ret = copy_restore_file_to_me($sd, $msa_restore_conf_file, $me_restore_conf_file);
  if ($ret != SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Copying revision $revision_id failed");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  $ret = restore_conf($sd, $me_restore_conf_file);
  if ($ret != SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "Restoring revision $revision_id failed");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Waiting the device (restore revision: $revision_id)");
  $ret = wait_until_device_is_up($sd);
  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'RESTORE', 'FAILED', "The device is unreachable after restoring the configuration (restore revision: $revision_id)");
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return SMS_OK;
  }

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'WORKING', "Backup of the restored configuration (restore revision: $revision_id)");

  require_once load_once('stormshield', 'do_backup_conf.php');

  sms_set_update_status($sms_csp, $sdid, SMS_OK, 'RESTORE', 'ENDED', "Restore done (restore revision: $revision_id)");
}
catch (Exception | Error $e)
{
  disconnect();
  sms_set_update_status($sms_csp, $sdid, $e->getCode(), 'RESTORE', 'FAILED', "Restore failure (restore revision: $revision_id)");
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return SMS_OK;
}

sms_sd_unlock($sms_csp, $sms_sd_info);

return SMS_OK;
