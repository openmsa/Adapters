<?php

// Asset management

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('nec_pflow_pfc', 'nec_pflow_pfc_connect.php');
require_once "$db_objects";

try
{
  // Connection
  nec_pflow_pfc_connect();

  $asset = array();
  $asset_attributes = array();

  /*
    PFC# show version
  Date: 2016-11-14 23:40:07 JST
  V6.3.2.0 build34

    # comment
    PFC don't have a model name such as PF6800.
  */

  $asset['model'] = '';

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version" ,"#");
  $show_version_patterns = array(
    'firmware' => '@V(?<firmware>.*)@',
  );

  foreach ($show_version_patterns as $name => $pattern)
  {
    if (preg_match($pattern, $buffer, $matches) > 0)
    {
      $asset[$name] = trim($matches[$name]);
    }
  }

/*
  PFC# show license
  Date: 2016-12-20 19:12:01 JST
  License Type:Initial
  --------------------------------------
  Expiry Date       :             <none>
  Allocated Licenses
     OFS            :                  4
     OFvS           :                  0
     OFIX           :                  0
  --------------------------------------

   # comment
     none
 */

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show license" ,"#");
  $show_license_patterns = array(
    'license' => '@License Type:(?<license>.*)@',
  );
  foreach ($show_license_patterns as $name => $pattern)
  {
    if (preg_match($pattern, $buffer, $matches) > 0)
    {
      $asset[$name] = trim($matches[$name]);
    }
  }

  /*
   #comment
   PFC don't have a serial number. 
  */
 
  $asset['serial'] = '';
  
  /*
    PFC# show resource status detail | grep -A 1 'MEMORY information'
  Date: 2016-11-14 23:39:03 JST
  MEMORY information:
    size:1877MB

   #comment
   PFC don't have a cpu information.
   */

  $asset['cpu'] = '';

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'show resource status detail | grep -A 1 \'MEMORY information\'' ,"#");
  $show_system_cpu_patterns = array(
    'memory' => '@.*size:(?<memory>.*)@'
  );
  foreach ($show_system_cpu_patterns as $name => $pattern)
  {
    if (preg_match($pattern, $buffer, $matches) > 0)
    {
      $asset[$name] = trim($matches[$name]);
    }
  }

  debug_dump($asset, "asset:\n");

  // Set standard information
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  // Set extended information
  foreach ($asset_attributes as $name => $value)
  {
    $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
    if ($ret !== 0)
    {
      throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
    }
  }

  nec_pflow_pfc_disconnect();
}
catch (Exception $e)
{
  nec_pflow_pfc_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage () . "\n");
  return $e->getCode();
}

return SMS_OK;

?>
(END)

