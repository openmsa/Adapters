<?php
/*
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
require_once load_once('esw500', 'esw500_connect.php');
require_once "$db_objects";


try
{
  // Connection
  esw500_connect();

  $asset = array();
  $asset_attributes = array();

  // serial number
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show system id");
  printf("buffer: $buffer \n");

	//Lister CPU

	$regextcam = '@(five.*%$)@';
	$buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show cpu utilization");
	$line = get_one_line($buffer);

		while ($line !== false)
	{
		if (preg_match($regextcam,$line,$matches) >0 )
		{
			$asset['cpu'] = trim($matches[1]);
		}

		$line = get_one_line($buffer);
	}


  $line = strstr($buffer, "Serial number");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^Serial\s*number\s*:\s*(.*)@', $line, $result) > 0)
    {
      $asset['serial'] = trim($result[1]);
      echo "SerialNumber [".$asset['serial']."]\n";
    }
  }

  // firmware
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show version");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "SW version");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^SW\s*version\s*(.*)@', $line, $result) > 0)
    {
      $asset['firmware'] = trim($result[1]);
      echo "firmware [".$asset['firmware']."]\n";
    }
  }

  // model
  $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show system");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "System Description");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^System\s*Description:\s*(.*)@', $line, $result) > 0)
    {
      $asset['model'] = trim($result[1]);
      echo "model [".$asset['model']."]\n";
    }
  }

  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  /**************************  ASSET ************************************/

	// Lister des interfaces
	$regexinterface = '@^(g[0-9]{1,2}).*(Full|Half|--).*(100|10|1000|--).*(Up|Down)@';
	$buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show interfaces status");
	$line = get_one_line($buffer);

		while ($line !== false)
	{
		if (preg_match($regexinterface,$line,$matches) >0 )
		{
			$asset_attributes[trim($matches[1])] = trim($matches[4])." ".trim($matches[3])." ".trim($matches[2])." Duplex"." / ";
		}

		$line = get_one_line($buffer);
	}

	// Lister show power inline

	$regexpower = '@^\s+(g[0-9]{1,2})\s(.*)@';
	$buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show power inline");
	$line = get_one_line($buffer);

		while ($line !== false)
	{
		if (preg_match($regexpower,$line,$matches) >0 )
		{
			$asset_attributes[trim($matches[1])] .= trim($matches[2]);
		}

		$line = get_one_line($buffer);
	}


	//Lister TCAM

	$regextcam = '@(TCAM utilization):(.*)@';
	$buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show system tcam utilization");
	$line = get_one_line($buffer);

		while ($line !== false)
	{
		if (preg_match($regextcam,$line,$matches) >0 )
		{
			$asset_attributes[trim($matches[1])] = trim($matches[2]);
		}

		$line = get_one_line($buffer);
	}



	// Tableau d'expressions réliè de show system
	$show_system_attributes_patterns = array(
	'@(System Up Time \(days,hour:min:sec\)):(.*)@',
	'@(System Contact):(.*)@',
	'@(System Name):(.*)@',
	'@(System Location):(.*)@',
	'@(System MAC Address):(.*)@',
	'@(Main Power Supply Status):(.*)@',
	'@(System Object ID):(.*)@',
	'@(Fan \d Status):(.*)@'
	);


	$buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show system");

	$line = get_one_line($buffer);
	while ($line !== false)
	{
		foreach ($show_system_attributes_patterns as $pattern)
		{
			if (preg_match($pattern, $line, $matches) > 0)
			{
				$asset_attributes[trim($matches[1])] = trim($matches[2]);
			}
		}

		$line = get_one_line($buffer);

	}



foreach ($asset_attributes as $name => $value)
{

	if (empty($value))
	{
		$value = "N/A";
	}

	$ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
	if ($ret !== 0)
	{
    throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
	}
}


esw500_disconnect();

}

catch (Exception | Error $e)
{
  esw500_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>
