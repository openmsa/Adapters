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
require_once load_once('esa', 'esa_connect.php');
require_once "$db_objects";


try
{
	// Connection
	esa_connect();

	$asset = array();
	$asset_attributes = array();

	// GET ASSETS
	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "version");

	$show_ver_asset_patterns = array(
			'serial' => '@Serial #: (?<serial>.*)@',
			'firmware' => '@Version: (?<firmware>.*)@',
			'model' => '@Model: (?<model>.*)@',
			'memory' => '@Memory: (?<memory>.*)@',
			'cpu' => '@CPUs: (?<cpu>.*)@'

	);

	$show_ver_asset_attributes_patterns = array(
	    '@^(?<name>BIOS): (?<value>.*)$@m',
	    '@^(?<name>Install Date): (?<value>.*)$@m',
	    '@^(?<name>Build Date): (?<value>.*)$@m',
	    '@^(?<name>Version): (?<value>.*)$@m'
	);

	foreach ($show_ver_asset_attributes_patterns as $pattern)
	{
	  if (preg_match($pattern, $buffer, $matches) > 0)
	  {
	    $asset_attributes[trim($matches['name'])] = trim($matches['value']);
	  }
	}

	//debug_dump($asset_attributes);

	$line = get_one_line($buffer);
	while ($line !== false)
	{
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
	}

	foreach ($asset_attributes as $name => $value)
	{
	  $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
	  if ($ret !== 0)
	  {
      throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
	  }
	}


	// Get License information
	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "showlicense");

	$expired_array = array('@License has Expired@');
	foreach ($expired_array as $error)
	{
		if (preg_match($error, $buffer) > 0)
		{
			$asset['license'] = "License has Expired";
		}
	}

	$error_array = array('@Unknown command@');
	foreach ($error_array as $error)
	{
		if (preg_match($error, $buffer) > 0)
		{
			$asset['license'] = "No License Needed.";
		}
	}


	$suceeded_array = array('@Virtual License@');
	foreach ($suceeded_array as $success)
	{
		if (preg_match($success, $buffer) > 0)
		{

			$line = get_one_line($buffer);
			while ($line !== false)
			{
				$pattern = array ('@end_date:@', '@end_date@');
				foreach($pattern as $string) {
					if (preg_match($string, $line) > 0)
					{
						$line = str_replace("end_date", "End date", $line);
						$asset['license'] = $line;
					}
				}
				$line = get_one_line($buffer);
			}
		}
	}


	$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
	if ($ret !== 0)
	{
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
	}

	esa_disconnect();

}

catch (Exception $e)
{
	esa_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>
