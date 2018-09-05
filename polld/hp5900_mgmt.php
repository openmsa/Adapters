<?php
/*
 * Available global variables
 *      $sms_sd_ctx        pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info       sd_info structure
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $admin_proto       protocol used to administrate routers
 *   SD_ADMIN_PROTO_NONE       0
 *   SD_ADMIN_PROTO_TELNET     1
 *   SD_ADMIN_PROTO_SSH1_DES   2
 *   SD_ADMIN_PROTO_SSH1_3DES  3
 *   SD_ADMIN_PROTO_SSH2       4
 *
 */

// Asset management

require_once 'smserror/sms_error.php'; 
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
//[START] Modified 080117 NSP-flojo.fh
//Modified Device Adaptor Name
require_once load_once('hp5900', 'hp5900_connect.php');
//[END] Modified 080117 NSP-flojo.fh

try
{
  $ret = device_connect(); 
   
   if ($ret !== SMS_OK)
  {
    return $ret;
  }
  global $sms_sd_ctx;
  $asset_attributes = array();
  $asset = array();
  $show_ver_asset_patterns = array(
  'memory' => '@Mem:\s+(?<memory>\S*)@',
  );

//[START] Modified 080117 NSP-flojo.fh
//Modified Commands sent to the device
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "display memory",$sms_sd_ctx->getPrompt());
//[END] Modified 080117 NSP-flojo.fh
  $line = get_one_line($buffer);
  while ($line !== false)
  {
   	foreach ($show_ver_asset_patterns as $name => $pattern)
  	{
  		if (preg_match($pattern, $line, $matches) > 0)
  		{
  			$asset[$name] = trim($matches[$name]);
  		}
  	}
  	if (isset($asset))
  	{
  		foreach ($asset as $name => $value)
  		{
  			unset($show_ver_asset_patterns[$name]);
  		}
  	}
  	$line = get_one_line($buffer);
  }
  
  $show_ver_asset_patterns2 = array(
  'model' => '@BOARD\s+TYPE:\s+(?<model>\S*\s+\S*)@',
  //'cpu' => '@DRAM:\s+(?<cpu>\S*\s+\S*)@',     // NCOS bugfix 2017.09.25
  'firmware' => '@Boot\s+image\s+version:\s+(?<firmware>\S*\s+\S*\s+\S*)@',
  );
  
//[START] Modified 080117 NSP-flojo.fh
//Modified Commands sent to the device 
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "display version",$sms_sd_ctx->getPrompt());
//[END] Modified 080117 NSP-flojo.fh 
  $line = get_one_line($buffer);
  while ($line !== false)
  {
   	foreach ($show_ver_asset_patterns2 as $name => $pattern)
  	{
  		if (preg_match($pattern, $line, $matches) > 0)
  		{
  			$asset[$name] = trim($matches[$name]);
  		}
  	}
  	if (isset($asset))
  	{
  		foreach ($asset as $name => $value)
  		{
  			unset($show_ver_asset_patterns[$name]);
  		}
  	}
  	$line = get_one_line($buffer);
  }
  
  
//[START] Modified 080117 NSP-flojo.fh
//Added another set for Serial 
  $show_ver_asset_patterns3 = array(
   'serial' => '@DEVICE_SERIAL_NUMBER\s+:\s+(?<serial>\S*)@',
  );
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "display device manuinfo",$sms_sd_ctx->getPrompt());
  $line = get_one_line($buffer);
  while ($line !== false)
  {
	//ADDED so that reading will stop upon reaching Fan data (not relevant)
	sms_log_info("Current line : ".$line);
	if($line == "Fan 1:"){
			sms_log_info("Break here!".$line);
			break;
    }
	//END
	
   	foreach ($show_ver_asset_patterns3 as $name => $pattern)
  	{
  		if (preg_match($pattern, $line, $matches) > 0)
  		{
  			$asset[$name] = trim($matches[$name]);
  		}

  	}
  	if (isset($asset))
  	{
  		foreach ($asset as $name => $value)
  		{
  			unset($show_ver_asset_patterns[$name]);
  		}
  	}
  	$line = get_one_line($buffer);
  }
//[END] Modified 080117 NSP-flojo.fh   
  
  // NCOS bugfix 2017.09.25
  // set cpu to "(unknown)"
  $asset['cpu'] = '(unknown)';
  
  sms_log_info("ASSETS : ".json_encode($asset));
  sms_log_info("ASSETS ATTRIBUTES : ".json_encode($asset_attributes));
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }
  
  device_disconnect();
}
catch (Exception $e)
{
  device_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>

