<?php
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('fortinet_generic', 'common.php');
require_once "$db_objects";

// -------------------------------------------------------------------------------------
// PROVISIONING
// -------------------------------------------------------------------------------------
function prov_license_upload($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $SD;
  global $ipaddr;
  global $login;
  global $passwd;
  global $port;
  global $datacenter_ip;
  global $sms_sd_ctx;
  global $sendexpect_result;


  $conf = new fortinet_generic_configuration($sdid);
  $datacenter_ip = $conf->get_additional_vars('DATACENTER_IP');
  $tftp_Server_Address = $datacenter_ip;
  $tmp_dir = "{$SD->SDID}_" . rand(100000, 999999);
  $tftp_dir = $_SERVER['TFTP_BASE'] . "/" . $tmp_dir;
  $rebooting = false;
  try
  {
    // Get license
    $ret = get_repo_files_map($map_conf, $error, 'License');
    if ($ret !== SMS_OK)
    {
      // xml entity file broken
      throw new SmsException("No license file specified", $ret);
    }
    if (!empty($map_conf))
    {
      foreach ($map_conf as $mkey => $file)
      {
        if (!empty($file))
        {
          $file_name = basename($file);
          $license_file = $_SERVER['FMC_REPOSITORY'] . "/$file";
          break; // use this first file found
        }
      }
    }
    if (empty($license_file))
    {
      throw new SmsException("No license file specified", ERR_SD_LICENSE_UPDATE);
    }

    mkdir($tftp_dir, 0755);
    copy($license_file, "$tftp_dir/$file_name");
    $command = "execute restore vmlicense tftp $tmp_dir/$file_name $tftp_Server_Address";
    // exec_cmd_expect($sms_csp, $commands, $sms_sd_info, $stage, "License Upload Failed");
    $errormsg = "License Upload Failed";
    $tab[0] = "#";
    $tab[1] = "y/n)";

    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $command, $tab);
    if ($index === -1)
    {
      throw new Exception($errormsg, ERR_SD_LICENSE_UPDATE);
    }
    if ($index === 1)
    {
      unset($tab);
      $tab[0] = "from tftp server OK";
      $tab[1] = "install failed";
      $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "y", $tab);
      if ($index === -1)
      {
        throw new Exception($errormsg, ERR_SD_LICENSE_UPDATE);
      }
      if ($index === 0)
      {
        $rebooting = true;
      }
      if ($index === 1)
      {
        throw new Exception($sendexpect_result, ERR_SD_LICENSE_UPDATE);
      }
    }

    rmdir_recursive($tftp_dir);

    if ($rebooting)
    {
      fortinet_generic_disconnect();

      $conf = new fortinet_generic_configuration($sdid, true);
      $ret = $conf->wait_until_device_is_up(120);
      if ($ret == SMS_OK)
      {
        //device is not ready for ssh, but pingable
        sleep(5);
        $loop = 5;
        while ($loop > 0)
        {
         // UTM device closed the connection when the license status change from Pending to Valid, we have to reconnect, this explain why we put this loop
          sleep(10); // wait for ssh to come up
          $ret = fortinet_generic_connect($ipaddr, $login, $passwd, $port);
          if ($ret == SMS_OK)
          {
            break;
          }
          $loop--;
        }
        if ($ret != SMS_OK)
        {
          $status_message = "Cannot connect to the device after reboot";
          sms_log_error(__FILE__ . ':' . __LINE__ . " : $status_message\n");
          throw new Exception("Connection Failed", $ret);
        }
      }
      else
      {
        throw new Exception("Connection Failed", $ret);
      }
    }
  }
  catch (SmsException $e)
  {
    rmdir_recursive($tftp_dir);
    throw $e;
  }

  return SMS_OK;
}

?>