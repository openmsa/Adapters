<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('pfsense_fw', 'pfsense_fw_connect.php');
require_once load_once('pfsense_fw', 'apply_errors.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 */
function pfsense_fw_apply_conf($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $SMS_OUTPUT_BUF;

  $network = get_network_profile();
  $SD = &$network->SD;

  $ipaddr = $sms_sd_ctx->getIpAddress();
  $login = $sms_sd_ctx->getLogin();
  $passwd = $sms_sd_ctx->getPassword();

  /*if (strlen($configuration) !== 0)
  {
    // Validate XML File
    try
    {
      $configLineArray = explode("\n", $configuration);

      $configTmp = "";
      $pos = strpos($configLineArray[0], "OK");
      if ($pos === false)
      {
        $configTmp = $configuration;
      }
      else
      {
        unset($configLineArray[0]);
        foreach ($configLineArray as $line)
        {
          $configTmp .= $line . "\n";
        }
      }

      $configTmp = preg_replace('/SMS_OK/', '', $configTmp);

      $xslDoc = new DOMDocument();
      $xslDoc->load("/opt/sms/bin/php/pfsense_fw/ordering.xsl");

      $xmlDoc = new DOMDocument();
      $xmlDoc->loadXML($configTmp);

      $proc = new XSLTProcessor();
      $proc->importStylesheet($xslDoc);
      $xmlConfig = $proc->transformToXML($xmlDoc);

      if (!$xmlConfig)
      {
        throw new Exception('XML PARSING FAILED');
      }
    }
    catch (Exception | Error $e)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ': ' . $e->getMessage() . "\n");
      return ERR_CONFIGURATION_INVALID;
    }
  }

  update_password($sms_sd_ctx, $passwd, $SD->SD_PASSWD_ENTRY);
 */ 
  $passwd = $SD->SD_PASSWD_ENTRY;

  if (strlen($configuration) === 0)
  {
    return SMS_OK;
  }

 // debug_dump($xmlConfig, 'CONFIG TO APPLY');

debug_dump($configuration, 'CONFIG TO APPLY');

  $file_name = "$sdid.cfg";

  // Create the file
  $local_file_name = $_SERVER['TFTP_BASE'] . "/" . $file_name;
  if (file_put_contents($local_file_name, $configuration) === false)
  {
    sms_log_error(__FILE__ . ':' . __LINE__ . ": file_put_contents(\"$local_file_name\", \"...\") failed\n");
    unlink($local_file_name);
    return ERR_LOCAL_FILE;
  }

  $src = $local_file_name;
  $dst = "/cf/conf/config.xml";
  $sd_mgt_port = $SD->SD_MANAGEMENT_PORT;

 $scp_cmd="/opt/sms/bin/sms_scp_transfer -s $src -d $dst -l root -p '".$passwd."'  -a $ipaddr -P $sd_mgt_port";
  $ret_scp = exec_local(__FILE__ . ':' . __LINE__, $scp_cmd, $output);
  unlink($local_file_name);

  if ($ret_scp !== SMS_OK)
  {
    return $ret_scp;
  }

  $scp_ok = false;
  foreach ($output as $line)
  {
    if (strpos($line, 'SMS-CMD-OK') !== false)
    {
      $scp_ok = true;
      break;
    }
  }

  if ($scp_ok === false)
  {
    foreach ($output as $line)
    {
      sms_log_error($line);
    }
    return ERR_SD_SCP;
  }

  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');

  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "/etc/rc.reload_all");
/*
  $sendexpect_result = '';
  $SMS_OUTPUT_BUF = '';

  $tab[0] = 'active.';
  $tab[1] = 'No changes detected from current configuration.';
  $tab[2] = $sms_sd_ctx->getPrompt();
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
  $SMS_OUTPUT_BUF .= $sendexpect_result;

  switch ($index)
  {
    case 0:
      $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "commit");

      unset($tab);
      $tab[0] = '[]>';
      $tab[1] = 'There is no data to commit.';
      $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
      $SMS_OUTPUT_BUF .= $sendexpect_result;

      if ($index === 0)
      {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'Updated from MSA');
        unset($tab);
        $tab[0] = 'Changes committed';
        $tab[1] = 'Do you want to save the current configuration for rollback';
        $index2 = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);
        if ($index2 === 1)
        {
          $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, '');
          $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, "Changes committed");
        }
        $SMS_OUTPUT_BUF .= $sendexpect_result;
      }
      else if ($index !== 1)
      {
        save_result_file($SMS_OUTPUT_BUF, "conf.error");
        return ERR_SD_CMDFAILED;
      }
      break;

    case 1:
      break;

    default:
      save_result_file($SMS_OUTPUT_BUF, "conf.error");
      return ERR_SD_CMDFAILED;
  }
*/
  save_result_file($SMS_OUTPUT_BUF, "conf.error");
/*
  foreach ($apply_errors as $apply_error)
  {
    if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
      return ERR_SD_CMDFAILED;
    }
  }
*/
  return SMS_OK;
}
function update_password($sms_sd_ctx, $old_passwd, $new_password)
{
  if ($old_passwd === $new_password)
  {
    return;
  }

  $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, 'passwd', 'Old password:');

  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $old_passwd);
  unset($tab);
  $tab[0] = '[]>';
  $tab[1] = 'New Password:';
  $tab[2] = 'New password:';
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

  $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, $new_password);
  unset($tab);
  $tab[0] = '[]>';
  $tab[1] = 'Retype new password:';
  $tab[2] = 'Please enter the new password again:';
  $index = $sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, $tab);

  $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $new_password);
}

?>
