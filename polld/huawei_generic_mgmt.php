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
require_once load_once('huawei_generic', 'device_connect.php');

try
{
  // Connection
  $ret = device_connect();
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  $asset_attributes = array();

  $asset_patterns = array(
      'display esn' => array(
          'serial' => '@ESN of device: (?<serial>\S*)@'
      ),
      'display version' => array(
          'firmware' => '@VRP \(R\) software, Version (?<firmware>.*)@',
          'model' => '@(?<model>Huawei \w+) Router uptime@'
      ),
      'display version slot 0' => array(
          'memory' => '@SDRAM Memory Size    : (?<memory>.*)@'
      )
  );

  foreach ($asset_patterns as $cmd => $patterns)
  {
    $buffer = $sms_sd_ctx->sendexpectone(__FILE__ . ':' . __LINE__, $cmd);
    $line = get_one_line($buffer);
    while ($line !== false)
    {
      // regular asset fields
      foreach ($patterns as $name => $pattern)
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
          unset($patterns[$name]);
        }
      }

      $line = get_one_line($buffer);
    }
  }

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