<?php
/*
 * Date : Sep 24, 2007
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('fortinet_generic', 'fortinet_generic_connect.php');
require_once "$db_objects";

$Monthes = array (
    "Jan" => "01",
    "Feb" => "02",
    "Mar" => "03",
    "Apr" => "04",
    "May" => "05",
    "Jun" => "06",
    "Jul" => "07",
    "Aug" => "08",
    "Sep" => "09",
    "Oct" => "10",
    "Nov" => "11",
    "Dec" => "12",
);

function format_date($date)
{
  global $Monthes;

  if (strpos('N/A', $date) !== false)
  {
    return '';
  }
  /*
   * Date can have the 2 following format :
   *  Day Month DD YYYY
   *  Day Month DD HH:mm:ss YYYY
   * convert it into YYYY/MM/DD 00:00:00
   */
  $date = str_replace('  ', ' ', $date);
  if (strpos($date, ':') !== false)
  {
    // Day Month DD HH:mm:ss YYYY
    list($d, $month, $day, $hour, $year) = explode(' ', $date);
  }
  else
  {
    // Day Month DD YYYY
    list($d, $month, $day, $year) = explode(' ', $date);
    $hour = '00:00:00';
  }
  $day = str_pad($day, 2, '0', STR_PAD_LEFT);
  return "$year/".$Monthes[$month]."/$day $hour";
}

try
{

	// Connection
	fortinet_generic_connect();

	// Check if the firewall is in a cluster
	$net_conf = get_network_profile();
	$sd = & $net_conf->SD;
	$prompt = $sms_sd_ctx->getPrompt();
	/* if ($sd->SD_HSRP_TYPE !== 0)
	{
		if ($sd->SD_HSRP_TYPE === 2)
		{
			sms_set_ha_peer_status($sd_poll_elt, 1);
		}
		// store status of passive device in slave sd
		 if ($sd->SD_HSRP_TYPE === 2)
		{
			$cmd = "get sys ha status";

			$info_peer = sendexpectone(__LINE__, $sms_sd_ctx, $cmd, '#');
			//Slave :100 FortiVM_v524_02-HA FGVM040000030030 1
			if(preg_match("/Slave\s:\d+\s\S+\s\S+\s(?<slave_id>[0-9]+)/",$info_peer,$match) > 0)
			{
				$slave_Id = $match["slave_id"];

				$cmd = "execute ha manage $slave_Id";
				$ret = sendexpectone(__LINE__, $sms_sd_ctx, $cmd, '$');
				if(!$ret)
				{
					sms_set_ha_peer_status($sd_poll_elt, 0);
					fortinet_generic_disconnect();
					sms_log_error("Exception occur: " . $e->getMessage() . "\n");
					return $e->getCode();
				}
				else
				{
					$prompt = '$';
					sms_set_ha_peer_status($sd_poll_elt, 1);
				}
			}
		}
	}  */

	$asset = array();

	$get_system_status_asset_patterns = array(
	    'firmware'    => '@Version:\s+(?<firmware>.*)@',
	    'av_version'  => '@Virus-DB:\s+(?<av_version>.*)@',
	    'ips_version' => '@IPS-DB:\s+(?<ips_version>.*)@',
	    'serial'      => '@Serial-Number:\s+(?<serial>.*)@',
	    'license'     => '@License Status: (?<license>.*)@',
	);

	$get_hardware_status_asset_patterns = array(
	    'model'  => '@Model name:\s+(?<model>.*)@',
	    'cpu'    => '@CPU:\s+(?<cpu>.*)@',
	    'memory' => '@\\nRAM:\s+(?<memory>.*)@',
	);

	$get_system_fortiguard_asset_patterns = array(
	    'as_expiration'  => '@antispam-expiration\s+:\s+(?<as_expiration>.*)@',
	    'av_expiration'  => '@avquery-expiration\s+:\s+(?<av_expiration>.*)@',
	    'url_expiration' => '@webfilter-expiration\s+:\s+(?<url_expiration>.*)@',
	);


	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system status', $prompt);

	$line = get_one_line($buffer);
	while ($line !== false)
	{
		foreach ($get_system_status_asset_patterns as $name => $pattern)
		{
			if (preg_match($pattern, $line, $matches) > 0)
			{
				$asset[$name] = trim($matches[$name]);
			}
		}

		// remove already used patterns
		if (isset($asset))
		{
			foreach ($asset as $name => $value)
			{
				unset($get_system_status_asset_patterns[$name]);
			}
		}

		$line = get_one_line($buffer);
	}


	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get hardware status', $prompt);

	$line = get_one_line($buffer);
	while ($line !== false)
	{

		foreach ($get_hardware_status_asset_patterns as $name => $pattern)
		{
			if (preg_match($pattern, $line, $matches) > 0)
			{
				$asset[$name] = trim($matches[$name]);
			}
		}

		// remove already used patterns
		if (isset($asset))
		{
			foreach ($asset as $name => $value)
			{
				unset($get_hardware_status_asset_patterns[$name]);
			}
		}

		$line = get_one_line($buffer);
	}


	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system fortiguard', $prompt);

	$line = get_one_line($buffer);
	while ($line !== false)
	{
		foreach ($get_system_fortiguard_asset_patterns as $name => $pattern)
		{
			if (preg_match($pattern, $line, $matches) > 0)
			{
				$asset[$name] = format_date(trim($matches[$name]));
			}
		}

		// remove already used patterns
		if (isset($asset))
		{
			foreach ($asset as $name => $value)
			{
				unset($get_system_fortiguard_asset_patterns[$name]);
			}
		}

		$line = get_one_line($buffer);
	}

	if($prompt != $sms_sd_ctx->getPrompt())
	{
		//exit from slave node.
		sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'exit');
	}

	$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
	if ($ret !== 0)
	{
	  debug_dump($asset, "Asset failed:\n");
	  throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
	}

	fortinet_generic_disconnect();

}
catch (Exception | Error $e)
{
	fortinet_generic_disconnect();
	sms_log_error("Exception occur: " . $e->getMessage() . "\n");
	return $e->getCode();
}

return SMS_OK;

?>