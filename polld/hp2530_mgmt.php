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
 *  [SWF#95] NSP Bugfix Support for HP2530 support for Asset Management 2017.07.13 ADD
 */

// Asset management

require_once 'smserror/sms_error.php'; 
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('hp2530', 'hp2530_connect.php');

try
{
  $ret = device_connect(); 
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
 global $sms_sd_ctx;
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
   if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $asset = array();
  $show_ver_asset_patterns = array(
  'serial' => '@Serial\s+Number\s+: (?<serial>\S*)@',
  //'license' => '@oftware\s+revision\s+: (?<license>\S*)@',
  //'firmware' => '@ROM\s+Version\s+: (?<firmware>\S*)@',
  //'model' => '@System\s+Name\s+: (?<model>\S*)@',
  //'cpu' => '@CPU\s+Util\s+\(\%\)\s+: (?<cpu>[[:digit:]])@',
  'memory' => '@Memory\s+-\s+Total\s+: (?<memory>\S*)@',
  );

  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show system",$sms_sd_ctx->getPrompt());
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
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
  'firmware' => '@Version:\s+(?<firmware>\S*)@',
  'model' => '@Product:\s+(?<model>\S*\s+\S*)@',
  'cpu' => '@CPU:\s+(?<cpu>\S*)@',
  );
  
   //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED START
  //$sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "exit","#");
  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show tech buffers",$sms_sd_ctx->getPrompt());
  //[BUG#17] NSP Bugfix 2017.08.30 MODIFIED END
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
  
  

  sms_log_info("ASSETS : ".json_encode($asset));
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
