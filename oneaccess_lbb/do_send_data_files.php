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
require_once load_once('oneaccess_lbb', 'common.php');
require_once load_once('oneaccess_lbb', 'adaptor.php');
require_once "$db_objects";

$fmc_repo = $_SERVER['FMC_REPOSITORY'];
$fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];
//$dst_disk = "flash";
$flash_file_transfer = false;

$addon = '';

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
try {
  if (!empty($map_repo))
  {
    $network = get_network_profile();
    $SD = &$network->SD;

    $flash_file_transfer = true;


    // connect to the router
    if ($flash_file_transfer)
    {
      sd_connect();

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

          if (strpos($file, "/extract/") !== false)
          {
          	$dst = substr($file, strpos($file, "/extract/") + strlen("/extract/"));
          	$sd_node_ip_addr = $SD->SD_NODE_IP_ADDR;

          	if($SD->SD_CONF_ISIPV6)
          	{
          		$sd_node_ip_addr = $_SERVER['SMS_ADDRESS_IPV6'];
          	}

          	$ret = send_file_to_router($src, "", $sd_node_ip_addr);
          	if ($ret !== SMS_OK)
          	{
          		break;
          	}
          	$ret = extract_to_router($src, $dst, $sdid);
          	if ($ret !== SMS_OK)
          	{
          		break;
          	}
          	continue;
          }
          elseif (strpos($file, "/flash/") !== false)
          {
          	$dst = substr($file, strpos($file, "/flash/") + strlen("/flash/"));
          	$sd_node_ip_addr = $SD->SD_NODE_IP_ADDR;

          	if($SD->SD_CONF_ISIPV6)
          	{
          		$sd_node_ip_addr = $_SERVER['SMS_ADDRESS_IPV6'];
          	}

          	$ret = send_file_to_router($src, $dst, $sd_node_ip_addr);
          	if ($ret !== SMS_OK)
          	{
          		break;
          	}
          }
          else
          {
          	echo "File [$file] not trasnferred to device\n";
          	continue;
          }
        }
      }

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
}
catch(Exception $e)
{
  sd_disconnect();
  sms_set_update_status($sms_csp, $sdid, $ret, 'SENDDATAFILES', 'FAILED', $e->getMessage());
  sms_sd_unlock($sms_csp, $sms_sd_info);
  return $e->getCode();
}

return $ret;

?>