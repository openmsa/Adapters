<?php
/*
 * Date : Sep 24, 2007
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure (slave or master if HA)
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave or master)
 */

// Script description

require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'netasq_configuration.php');

function format_date($date)
{
  // Convert date YYYY-MM-DD into YYYY/MM/DD HH:MM:SS
  list($year, $month, $day) = explode('-', $date);
  return "$year/$month/$day 00:00:00";
}

$net_conf = get_network_profile();
$sd = & $net_conf->SD;

if ($sd->SD_HSRP_TYPE === 2)
{
  // nothing is done for HA slave to avoid simultaneously connexions (slave and master at the same time)
  return SMS_OK;
}

// From here for HA device $sd_poll_elt is the master and $sd_poll_peer is the slave
try
{
  global $sms_sd_ctx;

  netasq_connect();

  // Get the conf on the router
  $conf = new netasq_configuration($sdid);
  $conf->get_info();

  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'system property', 'SRPClient>');
  while (($line = get_one_line($buffer)) !== false)
  {
    if (preg_match('@^Version="(.*)"@', $line, $result) > 0)
    {
      $asset['firmware'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": firmware [".$asset['firmware']."]\n";
    }
    else if (preg_match('@^ASQVersion="(.*)"@', $line, $result) > 0)
    {
      $asset['ips_version'] = "ASQ " . trim($result[1]);
      echo __FILE__.':'.__LINE__.": IPS version [".$asset['ips_version']."]\n";
    }
    else if (preg_match('@^Model="(.*)"@', $line, $result) > 0)
    {
      $asset['model'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": model [".$asset['model']."]\n";
    }
    else if (preg_match('@^SerialNumber="(.*)"@', $line, $result) > 0)
    {
      $asset['serial'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": SerialNumber [".$asset['serial']."]\n";
    }
      else if (preg_match('@^MachineType="(.*)"@', $line, $result) > 0)
    {
      $asset['cpu'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": Cpu [".$asset['cpu']."]\n";
    }
  }

  $section = '';
  $licence = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'system licence dump', 'SRPClient>');
  while (($line = get_one_line($licence)) !== false)
  {
    if (preg_match('@^\[(.*)\]@', $line, $result) > 0)
    {
      $section = $result[1];
    }
    else
    {
      switch ($section)
      {
        case 'Global':
          if (preg_match('@^Comment=(.*)@', $line, $result) > 0)
          {
            $asset['license'] = trim($result[1]);
            echo __FILE__.':'.__LINE__.": License [".$asset['license']."]\n";
          }
          break;

        case 'Date':
          if (preg_match('@^URLFiltering=(.*)@', $line, $result) > 0)
          {
            $date = trim($result[1]);
            if ($date !== '0000-00-00')
            {
              $asset['url_expiration'] = format_date($date);
              echo __FILE__.':'.__LINE__.": URLFiltering exp date [".$asset['url_expiration']."]\n";
            }
          }
          else if (preg_match('@^AntiSPAM=(.*)@', $line, $result) > 0)
          {
            $date = trim($result[1]);
            if ($date !== '0000-00-00')
            {
              $asset['as_expiration'] = format_date($date);
              echo __FILE__.':'.__LINE__.": AntiSPAM exp date [".$asset['as_expiration']."]\n";
              $asset['as_version'] = 'Vade Retro';
            }
          }
          else if (preg_match('@^Antivirus=(.*)@', $line, $result) > 0)
          {
            $date = trim($result[1]);
            if ($date !== '0000-00-00')
            {
              $asset['av_expiration'] = format_date($date);
              echo __FILE__.':'.__LINE__.": Antivirus exp date [".$asset['av_expiration']."]\n";
            }
          }
          else if (preg_match('@^Pattern=(.*)@', $line, $result) > 0)
          {
            $date = trim($result[1]);
            if ($date !== '0000-00-00')
            {
              $asset['ips_expiration'] = format_date($date);
              echo __FILE__.':'.__LINE__.": IPS exp date [".$asset['ips_expiration']."]\n";
            }
          }
          break;
      }
    }
  }

  $section = '';
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'config object urlgroup setbase', 'SRPClient>');
  while (($line = get_one_line($buffer)) !== false)
  {
    if (preg_match('@^\[(.*)\]@', $line, $result) > 0)
    {
      $section = $result[1];
    }
    else
    {
      switch ($section)
      {
        case 'Config':
          if (preg_match('@^URLFiltering=(.*)@', $line, $result) > 0)
          {
            $asset['url_version'] = trim($result[1]);
            echo __FILE__.':'.__LINE__.": URLFiltering version [".$asset['url_version']."]\n";
          }
          break;
      }
    }
  }

  $av_name = '';
  $av_version = '';
  $av_buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'config antivirus list', 'SRPClient>');
  while (($line = get_one_line($av_buffer)) !== false)
  {
    if (preg_match('@^\[(\d+)\]@', $line, $result) > 0)
    {
      $av_index = $result[1];
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "config antivirus show config=$av_index", 'SRPClient>');
      if (strpos($buffer, 'Selected=1') !== false)
      {
        $buffer = strstr($buffer, '[Config]');
        if ($buffer !== false)
        {
          get_one_line($buffer); // consume '[Config]'
          while (($av_line = get_one_line($buffer)) !== false)
          {
            if (strpos($av_line, '[') === 0)
            {
              break 2;
            }
            if (preg_match('@^Name=(.*)@', $av_line, $result) > 0)
            {
              $av_name = trim($result[1]);
              if (!empty($av_version))
              {
                break 2;
              }
            }
            else if (preg_match('@^Version=(.*)@', $av_line, $result) > 0)
            {
              $av_version = trim($result[1]);
              if (!empty($av_name))
              {
                break 2;
              }
            }
          }
        }
      }
    }
  }
  $asset['av_version'] = "$av_name $av_version";
  echo __FILE__.':'.__LINE__.": AntiVirus version [".$asset['av_version']."]\n";

  // Check if the firewall is in a cluster
  if (($sd->SD_HSRP_TYPE !== 0) && (!empty($sd_poll_peer)))
  {
    $master_active = true;

    // store status of passive device in slave sd
    $info_peer = sendexpectone(__LINE__, $sms_sd_ctx, 'ha info serial=passive', 'SRPClient>');
    if (strpos($info_peer, '100 code=') !== false)
    {
      if ((strpos($info_peer, 'State=Ready') !== false) || (strpos($info_peer, 'State=Running') !== false ) || (strpos($info_peer, 'Reply=1') !== false))
      {
        // passive is up
        sms_set_ha_peer_status($sd_poll_peer, 1);
      }
      else
      {
        // passive is down
        sms_set_ha_peer_status($sd_poll_peer, 0);
      }

      $firmware_peer = '';
      $model_peer = '';
      while (($line = get_one_line($info_peer)) !== false)
      {
        if (preg_match('@^Version="(.*)"@', $line, $result) > 0)
        {
          $firmware_peer = trim($result[1]);
          echo __FILE__.':'.__LINE__.": firmware peer [".$firmware_peer."]\n";
        }
        else if (preg_match('@^Model="(.*)"@', $line, $result) > 0)
        {
          $model_peer = trim($result[1]);
          echo __FILE__.':'.__LINE__.": model peer [".$model_peer."]\n";
        }
      }
    }
    else
    {
      // passive is down
      sms_set_ha_peer_status($sd_poll_peer, 0);
    }

    // In a cluster check if the responding firewall is the one corresponding to the current SD
    // Save the asset in the active node
    if ($sd->SD_SERIAL_NUMBER === $asset['serial'])
    {
      // Master is active
      sms_bd_set_ha_active_status($sd_poll_elt, 1);
      sms_bd_set_ha_active_status($sd_poll_peer, 0);
    }
    else
    {
      // Slave is active
      sms_bd_set_ha_active_status($sd_poll_elt, 0);
      sms_bd_set_ha_active_status($sd_poll_peer, 1);
      $master_active = false;
    }

    // unnecessary to update the serial number for a cluster
    unset($asset['serial']);
  }

  netasq_disconnect();
}
catch (Exception | Error $e)
{
  netasq_disconnect();
  return $e->getCode();
}

if (($sd->SD_HSRP_TYPE !== 0) && (!empty($sd_poll_peer)))
{
  if ($master_active)
  {
    // Store asset for master
    $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
    if ($ret !== 0)
    {
      sms_log_error(__FILE__.':'.__LINE__.": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) (master active) Failed\n");
      return $ret;
    }

    // Store asset for slave, same as master except firmware and model
    $asset['firmware'] = $firmware_peer;
    $asset['model'] = $model_peer;
    $ret = sms_polld_set_asset_in_sd($sd_poll_peer, $asset);
    if ($ret !== 0)
    {
      sms_log_error(__FILE__.':'.__LINE__.": sms_polld_set_asset_in_sd($sd_poll_peer, $asset) (slave passive) Failed\n");
      return $ret;
    }
  }
  else
  {
    // Store asset for slave
    $ret = sms_polld_set_asset_in_sd($sd_poll_peer, $asset);
    if ($ret !== 0)
    {
      sms_log_error(__FILE__.':'.__LINE__.": sms_polld_set_asset_in_sd($sd_poll_peer, $asset) (slave active) Failed\n");
      return $ret;
    }

    // Store asset for master, same as slave except firmware and model
    $asset['firmware'] = $firmware_peer;
    $asset['model'] = $model_peer;
    $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
    if ($ret !== 0)
    {
      sms_log_error(__FILE__.':'.__LINE__.": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) (master passive) Failed\n");
      return $ret;
    }
  }
}
else
{
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    sms_log_error(__FILE__.':'.__LINE__.": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
    return $ret;
  }
}

return SMS_OK;

?>