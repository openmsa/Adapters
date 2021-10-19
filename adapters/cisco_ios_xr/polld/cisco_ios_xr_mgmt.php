<?php

// Asset management

require_once 'smserror/sms_error.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('cisco_ios_xr', 'cisco_ios_xr_connect.php');

try
{
  // Connection
  $ret = cisco_ios_xr_connect();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $asset_attributes = array();

  $show_ver_asset_patterns = array(
  //'serial' => '@Processor board ID (?<serial>\S*)@',
  //'license' => '@oftware \((?<license>[^\)]*)\)@',
  'firmware' => '@\s+Version\s+:\s+(?<firmware>\S+)@',
  'model' => '@(?<model>Cisco[^,]*)@',
  //'cpu' => '@^.* \((?<cpu>[^\)]*)\) processor@',
  //'memory' => '@with (?<memory>\d*K/\d*K bytes) of memory@',
  );

  $show_ver_asset_attributes_patterns = array(
  '@^(?<value>\d*K bytes) of (?<name>[^\.]+)\.?\s$@',
  '@^(?<value>\d*) (?<name>.* interface)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* line)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* port)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* Radio)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* service engine)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* Module)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* \(SRE\))\s$@',
  '@with (?<value>\d*K bytes) of (?<name>.*)\.@',
  '@^ROM: (?<name>System Bootstrap), Version (?<value>[^,]*),@'
  );

  $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "term len 0");
  $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "term width 0");
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show version");
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

  	foreach ($show_ver_asset_attributes_patterns as $pattern)
  	{
  		if (preg_match($pattern, $line, $matches) > 0)
  		{
  			$asset_attributes[trim($matches['name'])] = trim($matches['value']);
  		}
  	}

  	$line = get_one_line($buffer);
  }

  /*
   sh ip int brief | excl Interface
  FastEthernet0/0            10.100.0.7      YES TFTP   up                    up
  FastEthernet0/1            unassigned      YES unset  up                    up
  FastEthernet0/1.1          10.101.134.254  YES TFTP   up                    up
  FastEthernet0/1.10         unassigned      YES unset  up                    up
  FastEthernet0/1.100        10.101.100.254  YES TFTP   up                    up
  NVI0                       10.100.0.7      YES unset  up                    up
  */
  $show_ip_patterns = '@^(?<name>\S+)\s+(?<ipaddr>\S+)\s+\S+\s+\S+\s+(?<status>\S+)\s+\S+\s*$@';

  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show ip int brief | excl Interface");
  $line = get_one_line($buffer);
  while ($line !== false)
  {
  	if (preg_match($show_ip_patterns, $line, $matches) > 0)
  	{
  		$name = trim($matches['name']);
  		$ipaddr = trim($matches['ipaddr']);
  		$status = trim($matches['status']);
  		$asset_attributes[$name] = "$ipaddr $status";
  	}

  	$line = get_one_line($buffer);
  }

  debug_dump($asset_attributes);


  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  foreach ($asset_attributes as $name => $value)
  {
  	$ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
  	if ($ret !== 0)
  	{
      throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
  	}
  }

  cisco_ios_xr_disconnect();
}
catch (Exception | Error $e)
{
  cisco_ios_xr_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>
