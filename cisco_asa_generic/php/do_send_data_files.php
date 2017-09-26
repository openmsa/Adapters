<?php
/*
 * Version: $Id$
* Created: Apr 26, 2011
* Available global variables
*  $sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
*  $sms_sd_info   sd_info structure
*  $sms_csp       pointer to csp context to send response to user
*  $sdid
*  $sms_module    module name (for patterns)
*  $param[1-4]	  optional parameters :
*					file_server_addr=1.2.3.4
*					ftp_login=login
*					ftp_passwd=passwd
*
*  $db_objects    PHP DB context
*/

// Verb JSSENDDATAFILES
require_once 'smsd/sms_common.php';
require_once load_once('cisco_asa_generic', 'adaptor.php');

require_once "$db_objects";


$fmc_repo = $_SERVER['FMC_REPOSITORY'];
$fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];

$index = 1;
$p = "param$index";
while (!empty($$p))
{
  // Parameters
  if (strpos($$p, 'file_server_addr=') !== false)
  {
    $file_server_addr = str_replace('file_server_addr=', '', $$p);
  }
  else if (strpos($$p, 'ftp_login=') !== false)
  {
    $ftp_login = str_replace('ftp_login=', '', $$p);
  }
  else if (strpos($$p, 'ftp_passwd=') !== false)
  {
    $ftp_passwd = str_replace('ftp_passwd=', '', $$p);
  }
  $index++;
  $p = "param$index";
}


if (empty($file_server_addr))
{
  // When address is not specified, the file server is the SOC
  $is_local_file_server = false;
}
else
{
  // When address is specified, the file server is a local file server
  $is_local_file_server = true;
}

$ret = get_repo_files_map($map_repo, $error, 'Datafiles');
if ($ret !== SMS_OK)
{
  // xml entity file broken
  return $ret;
}

function on_error()
{
  global $sms_csp;
  global $sms_sd_info;
  global $sdid;

  sd_disconnect();
  sms_set_update_status($sms_csp, $sdid, ERR_SD_CMDTMOUT, 'SENDDATAFILES', 'FAILED', '');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  exit (ERR_SD_CMDTMOUT);
}

echo "do_send_data_files.php $sdid \n";

$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return 0;
}

sms_set_update_status($sms_csp, $sdid, SMS_OK, 'SENDDATAFILES', 'WORKING', '');

sms_send_user_ok($sms_csp, $sdid, "");
sms_close_user_socket($sms_csp);

// Asynchronous mode, the user socket is now closed, the results are written in database

if (!empty($map_repo))
{
  $network = get_network_profile();
  $SD = &$network->SD;

  // connect to the router
  if (substr_count(implode($map_repo), 'flash') > 0)
  {
    $ret = sd_connect();
    if ($ret !== SMS_OK)
    {
      sms_log_error(__FILE__.':'.__LINE__.": sd_connect() failed\n");
      sms_set_update_status($sms_csp, $sdid, ERR_LOCAL_PHP, 'SENDDATAFILES', 'FAILED', 'Device connection failure');
      sms_sd_unlock($sms_csp, $sms_sd_info);
      return $ret;
    }
    $on_error_fct = 'on_error';

    foreach ($map_repo as $file)
    {
      if (!empty($file))
      {
        if ($is_local_file_server)
        {
          $src = $file;
        }
        else
        {
          $src = "{$fmc_repo}/{$file}";
        }
        if (strpos($file, '/flash/') !== false)
        {
          $dst = substr($file, strpos($file, '/flash/') + strlen('/flash/'));
          $ret = send_file_to_router($src, $dst);
          if ($ret !== SMS_OK)
          {
            break;
          }
        }
        else
        {
          echo "Problem to transfer file [$file]\n";
          continue;
        }

      }
    }

    unset($on_error_fct);
    sd_disconnect();
  }
  else
  {
    sms_set_update_status($sms_csp, $sdid, ERR_CONFIG_EMPTY, 'SENDDATAFILES', 'ENDED', 'No flash files associated to device');
    sms_sd_unlock($sms_csp, $sms_sd_info);
    return ERR_CONFIG_EMPTY;
  }

}
else
{
  sms_set_update_status($sms_csp, $sdid, ERR_CONFIG_EMPTY, 'SENDDATAFILES', 'ENDED', 'No files associated to device');
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return ERR_CONFIG_EMPTY;
}

if ($ret === SMS_OK)
{
  sms_set_update_status($sms_csp, $sdid, $ret, 'SENDDATAFILES', 'ENDED', '');
}
else
{
  sms_set_update_status($sms_csp, $sdid, $ret, 'SENDDATAFILES', 'FAILED', '');
}

sms_sd_unlock($sms_csp, $sms_sd_info);
return $ret;

?>
