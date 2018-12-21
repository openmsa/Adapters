<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management

require_once 'smserror/sms_error.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('nec_ix', 'nec_ix_connect.php');

try
{
  // Connection
  $ret = device_connect();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $asset_attributes = array();

  $show_ver_asset_patterns = array(
  'serial' => '@S/N: (?<serial>\S*)@',
  'license' => '@oftware \((?<license>[^\)]*)\)@',
  'firmware' => '@, Version (?<firmware>[^,]*),@',
  'model' => '@^(?<model>[^(]*) \(.*with \d+K bytes of memory@',
  'cpu' => '@^.* \((?<cpu>[^\)]*)\) processor@',
  'memory' => '@with (?<memory>\d*K bytes) of memory@',
  );

  $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "term len 0");
  $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "term width 512");
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

  	$line = get_one_line($buffer);
  }

  $buffer = $sms_sd_ctx->sendexpectone(__FILE__.':'.__LINE__, "show hardware");
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
