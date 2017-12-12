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

	$asset = array();

	$get_system_status_asset_patterns = array(
		'model'    => '@Version: (?<model>\S+)@',
	  'firmware'    => '@Version:(?<firmware>.*)@',
		'serial'      => '@Serial-Number:(?<serial>.*)@',
	);

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'get system status');

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


	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'diagnose hardware cpu list');
	if (preg_match('@model\s+name\s+:\s+(?<cpu>.*)@', $buffer, $matches) > 0)
	{
		$asset['cpu'] = trim($matches['cpu']);
	}

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'diagnose hardware mem list');
	if (preg_match('@MemTotal:\s+(?<memory>.*)@', $buffer, $matches) > 0)
	{
		$asset['memory'] = trim($matches['memory']);
	}

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'diagnose debug vm license');
	if (preg_match('@License\s+info\s+:\s+(?<license>.*)@', $buffer, $matches) > 0)
	{
		$asset['license'] = trim($matches['license']);
	}

	$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
	if ($ret !== 0)
	{
	  debug_dump($asset, "Asset failed:\n");
	  throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
	}

	fortinet_generic_disconnect();

}
catch (Exception $e)
{
	fortinet_generic_disconnect();
	sms_log_error("Exception occur: " . $e->getMessage() . "\n");
	return $e->getCode();
}

return SMS_OK;

?>