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
  'serial' => '@Processor board ID (?<serial>\S*)@',
  'license' => '@oftware \((?<license>[^\)]*)\)@',
  'firmware' => '@\), Version (?<firmware>[^,]*),@',
  'model' => '@^(?<model>[^(]*) \(.*with \d+K/\d+K bytes of memory@',
  'cpu' => '@^.* \((?<cpu>[^\)]*)\) processor@',
  'memory' => '@with (?<memory>\d*K/\d*K bytes) of memory@',
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

  $show_diag_patterns = '@^\t(?<name>.*)\s+:\s+(?<value>.*)$@';
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show diag 0");
  $line = get_one_line($buffer);
  while ($line !== false)
  {
    if (preg_match('@EEPROM format version@', $line) > 0)
    {
      break;
    }
    if (preg_match($show_diag_patterns, $line, $matches) > 0)
    {
      $name = trim($matches['name']);
      $value = trim($matches['value']);
      $asset_attributes[$name] = "$value";
    }

    $line = get_one_line($buffer);
  }


  debug_dump($asset_attributes);


  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show ip ips signature | inc SDF release");

  $line = strstr($buffer, "isco SDF release");
  if ($line !== false)
  {
  	$asset['ips_version'] = null;
  	if (preg_match("/[A-Z]+[0-9]+[.]+[0-9]+/", $line, $versions) > 0)
  		$asset['ips_version'] = $versions[0];
  	if (is_null($asset['ips_version']))
  	{
  		sms_log_error(__FILE__.":".__LINE__.": preg_match(\"/[A-Z]+[0-9]+[.]+[0-9]+/\", $line, $versions) Failed\n");
  		$asset['ips_version'] = '';
  	}
  	$asset['ips_version'] = trim($asset['ips_version']);
  }
  else
  {
  	$line = strstr($buffer, "Another IPS operation is accessing the signatures");
  	if ($line !== false)
  	{
  		sms_log_error(__FILE__.':'.__LINE__, ": waiting for current IPS operation to end...\n");
  	}
  	$asset['ips_version'] = '';
  }

  //EOS Specific, geting Product part number from Show ver  command if exist
  $show_ver_patterns = '@^(?<name>.*)\s+:\s+(?<value>.*)$@';
  $buffer =  $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show diag | inc FRU");
  $sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  $line = get_one_line($buffer);
  $PPN_indice=0;
  while ($line !== false)
  {
    if (preg_match($show_ver_patterns, $line, $matches) > 0)
    {
      $name = trim($matches['name']);
      $value = trim($matches['value']);
      if (strpos($name,'FRU')!==false){
        $name='Product Part Number (PPN) '.$PPN_indice;
        $PPN_indice++;
     }
     $asset_attributes[$name] = strtoupper("$value");
    }
    $line = get_one_line($buffer);
  }
  # Check if Product Part Number have been found if not get the default model attribut instead
	if (array_key_exists('Product Part Number (PPN) ',$asset_attributes)=== false){
		$asset_attributes['Product Part Number (PPN) 0']=strtoupper($asset['model']);
	}
  #End of EOS Specific

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
