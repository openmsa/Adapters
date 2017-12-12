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

// Script description
require_once 'smsd/sms_common.php';
require_once load_once('cisco_asa_generic', 'adaptor.php');
require_once "$db_objects";

function exit_error($line, $error)
{
  sms_log_error("$line: $error\n");
  sd_disconnect();
  exit($error);
}


try
{
  // Connection
  sd_connect();

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version");

  $show_ver_asset_patterns = array(
      'serial' => '@Serial Number: (?<serial>\S*)@',
      'firmware' => '@Software Version (?<firmware>\S+)@',
      'memory' => '@Hardware:\s+\S+ (?<memory>\d+ MB) RAM@',
      'model' => '@Hardware:\s+(?<model>[^,]+), \d+@',
      'cpu' => '@CPU (?<cpu>[^,]+),@',
      'license' => '@This platform has \S+ \S+ (?<license>.*)$@',
  );

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
    exit_error(__FILE__ . ':' . __LINE__, ": sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed\n");
  }

  //EOS Management Specific
  /*show modul

   Mod Card Type                                    Model              Serial No.
   --- -------------------------------------------- ------------------ -----------
   0 ASA 5510 Adaptive Security Appliance         ASA5510            JMX1026K0N2
   1 ASA_5500_Series_Content_Security_Services_Mo ASA-SSM-CSC-10-K9  JAF11105661

   Mod MAC Address Range                 Hw Version   Fw Version   Sw Version
   --- --------------------------------- ------------ ------------ ---------------
   0 0018.1900.2736 to 0018.1900.273a  1.1          1.0(11)2     8.4(2)
   1 0019.e8c9.90c4 to 0019.e8c9.90c4  1.0          1.0(11)2

   Mod SSM Application Name           Status           SSM Application Version
   --- ------------------------------ ---------------- --------------------------

   Mod Status             Data Plane Status     Compatibility
   --- ------------------ --------------------- -------------
   0 Up Sys             Not Applicable
   1 Unresponsive       Not Applicable

   CIS124128(config)#
   */
  $asset_attributes = array();

  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show module");
  $model_only = strstr($buffer, "Mod Card");
  $model_only = strstr($model_only, "Mod MAC", true);
  $pos_begin = strpos($model_only, "Model") - 2;
  $counter = 0;
  $PPN_indice = 0;
  foreach (preg_split("/((\r?\n)|(\r\n?))/", $model_only) as $line)
  {
    $line = trim($line);
    if ($counter > 1 and $line !== "")
    {
      // begin to work on the 3rd line and only if the line isn't empty
      $ppn = "";
      $ppn = substr($line, $pos_begin);
      $ppn = strstr($ppn, " ", true);
      echo $ppn . "\n";
      $name = 'Product Part Number (PPN) ' . $PPN_indice;
      $asset_attributes[$name] = "$ppn";
      $PPN_indice++;
    }
    $counter++;
  }
  //End EOS Specific


  // Extended asset management
  foreach ($asset_attributes as $name => $value)
  {
    $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
    if ($ret !== 0)
    {
      exit_error(__FILE__ . ':' . __LINE__, ": sms_sd_set_asset_attribute($name, $value) Failed\n");
    }
  }
  // End extended asset


  sd_disconnect();
}
catch (Exception $e)
{
  sd_disconnect();
  exit($e->getCode());
}

return 0;

?>