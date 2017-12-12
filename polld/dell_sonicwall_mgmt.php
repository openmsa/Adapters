<?php
/*
 * 	Available global variables
*  	$sms_sd_info       sd_info structure
*  	$sdid
*  	$sms_module        module name (for patterns)
*  	$sd_poll_elt       pointer on sd_poll_t structure
*   $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
*/


// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('dell_sonicwall', 'dell_sonicwall_connect.php');
require_once "$db_objects";


function format_date_ips($date)
{
	// Convert DD-mmm-YYYY into YYYY/MM/DD 00:00:00

	list( $month, $day, $year) = preg_split("@/@", $date);

	//$month = $Month_Num[$month];
	return "$year/$month/$day 00:00:00";
}

try
{
	// Connection
	dell_sonicwall_connect();

	$asset = array();
	$asset_attributes = array();

	// GET ASSETS
	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report");
	$show_ver_asset_patterns = array(
			'model' => '@Model                     :(?<model>.*)@',
			'firmware' => '@Firmware Version          :(?<firmware>.*)@',
			'serial' => '@Serial number             :(?<serial>.*)@',
			'rom_version' => '@ROM Version               :(?<rom_version>.*)@',
			'cpu' => '@Processor                 : "(?<cpu>.*)"@',
			'memory' => '@Memory                    :(?<memory>.*)@',
			//'license' => '@Nodes/Users                   :(?<license>.*)@',
	);


	$line = get_one_line($buffer);
	$i = 1;
	while ($line !== false)
	{
		// Firmware
		if($i == 9)
		{
			$asset['model'] = $line;
		}

		if($i == 13)
		{
			$asset['serial'] = $line;
		}

		if($i == 14)
		{
			$asset['firmware'] = $line;
		}

		if($i == 17)
		{
			$asset['rom_version'] = $line;
		}

		if($i == 22)
		{
			$asset['cpu'] = $line;
		}

		if($i == 25)

		{
			$asset['memory'] = $line;
		}

		// regular asset fields
		foreach ($show_ver_asset_patterns as $name => $pattern)
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
				unset($show_ver_asset_patterns[$name]);
			}
		}

		$line = get_one_line($buffer);
		$i++;
	}

	// Get asset license
	$pattern = '@The SonicWALL is\s+(?<license>.*).@';
	$line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report license");
	if(preg_match($pattern, $line, $matches) > 0)
	{
		$asset['license'] = trim($matches['license']);
	}


	// Get assset IPs expiration date
	$pattern = "@IPS\s+Expiration\s+Date\s+:\s+(?<ips_expiration>.\S+)@";
	$line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report intrusion-detection-prevention");
	if(preg_match($pattern, $line, $matches) > 0)
	{
		$asset['ips_expiration'] = trim($matches['ips_expiration']);
		$asset['ips_expiration'] = format_date_ips($asset['ips_expiration']);
	}

	$pattern = "@IPS\s+Expiration\s+Date\s+:\s+(?<ips_expiration>.\S+)@";
	$line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report intrusion-detection-prevention");
	if(preg_match($pattern, $line, $matches) > 0)
	{
		$asset['ips_expiration'] = trim($matches['ips_expiration']);
		$asset['ips_expiration'] = format_date_ips($asset['ips_expiration']);
	}


	// Get assset anti-virus expiration date
	$pattern = "@McAfee\s+License\s+Expiration Date\s+:\s+UTC\s+(?<av_expiration>.\S+)@";
	$line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report anti-virus");

	if(preg_match($pattern, $line, $matches) > 0)
	{
		$asset['av_expiration'] = trim($matches['av_expiration']);
		$asset['av_expiration'] = format_date_ips($asset['av_expiration']);
	}

	if(preg_match('@(?<av_version>.\S+)\s+License\s+Expiration Date@', $line, $matches) > 0)
	{
		$asset['av_version'] = trim($matches['av_version']);
	}

	// Get assset anti-spam expiration date
	$pattern = "@License\s+Expiry\s+:\s+(?<as_expiration>.\S+)@";
	$line = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report anti-spam");
	if(preg_match($pattern, $line, $matches) > 0)
	{
		$asset['as_expiration'] = trim($matches['as_expiration']);
		$asset['as_expiration'] = format_date_ips($asset['as_expiration']);
	}

	// Get Additional Information

	$show_system_infos_patterns = '@(?<name>.*)\s+:\s+(?<value>.\S+)@';
	$show_security_service_patterns = '@(?<name>.*)\s+:(?<value>.*)@';

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show tech-support-report");
	$line = get_one_line($buffer);
	$i = 1;
	while ($line !== false)
	{
		if($i == 10 || $i == 17 || $i == 28)
		{
			if (preg_match($show_system_infos_patterns, $line, $matches) > 0)
			{
				$name = trim($matches['name']);
				$value = trim($matches['value']);
				$asset_attributes[$name] = "$value";
			}
		}

		if($i == 38 || $i == 41 || $i == 42 || $i == 45 || $i == 46 || $i == 47 || $i == 48 || $i == 49 || $i == 50)
		{
			if (preg_match($show_security_service_patterns, $line, $matches) > 0)
			{
				$name = trim($matches['name']);
				$value = trim($matches['value']);
				$asset_attributes[$name] = "$value";
			}
		}

		$line = get_one_line($buffer);
		$i++;
	}


	foreach ($asset_attributes as $name => $value)
	{
	  $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
	  if ($ret !== 0)
	  {
      throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
	  }
	}

	$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
	if ($ret !== 0)
	{
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
	}

	dell_sonicwall_disconnect();
}

catch (Exception $e)
{
	dell_sonicwall_disconnect();
	sms_log_error("Exception occur: " . $e->getMessage() . "\n");
	return $e->getCode();
}

return SMS_OK;

?>
