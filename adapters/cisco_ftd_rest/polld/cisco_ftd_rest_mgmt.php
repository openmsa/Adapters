<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

require_once 'smsd/sms_common.php';
require_once load_once('cisco_ftd_rest', 'me_connect.php');

$ret =  me_connect();
if ($ret != SMS_OK)
{
  return $ret;
}

try
{
  // command data
  $data = array(
      'commandInput' => "show version",
      'timeout' => 0,
      'type' => 'Command');
  $data = json_encode($data);
  $cmd = "POST#/api/fdm/latest/action/command#{$data}";
  $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
  $cmd_output = $sms_sd_ctx->get_array_response()['commandOutput'];

  debug_dump($cmd_output, 'SHOW VERSION');

  $show_ver_asset_patterns = array(
      'model' => '@Model\s+:\s+(?<model>.*)\s+Version.*@',
      'firmware' => '@Model\s+:\s+.*Version\s+(?<firmware>.*)@',
      'serial' => '@Serial Number:\s+(?<serial>\S*)@',
      'memory' => '@Hardware:\s+\S+\s+(?<memory>\d+\s+MB)\s+RAM@',
      'cpu' => '@Hardware:.*RAM,\s+CPU\s+(?<cpu>.*)@');

  $asset = array();
  $line = get_one_line($cmd_output);
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

    $line = get_one_line($cmd_output);
  }

  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    $err = ' sms_polld_set_asset_in_sd Failed';
    if (isset($asset))
    {
      $err .= "\n";
      foreach ($asset as $name => $value)
      {
        $err .= "'$name' => '$value', ";
      }
      $err = rtrim($err, ', \n\r\t\v\x00');
    }
    throw new SmsException($err, ERR_DB_FAILED);
  }
}
catch (Exception | Error $e)
{
  me_disconnect();
  sms_log_error('Exception occur: ' . $e->getMessage() . "\n");
  $SMS_OUTPUT_BUF = $e->getMessage();
  return $e->getCode();
}

me_disconnect();

return 0;

?>
