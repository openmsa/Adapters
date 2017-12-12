<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 *
 */

// Asset management

require_once 'smsd/sms_common.php';
require_once load_once('srp520', 'srp520_connection.php');
require_once load_once('srp520', 'srp520_configuration.php');


try
{
  $conn = new srp520_connection();
  $conf = new srp520_configuration($sdid);
  $running_conf = $conf->get_running_conf($conn);

  $asset = array();

  $dom = new DOMDocument;
  $dom->preserveWhiteSpace = false;

  if($dom->loadXML($running_conf, LIBXML_NOERROR|LIBXML_NOWARNING ) === FALSE)
  {
   	throw new SmsException($error_formated, ERR_CONFIG_EMPTY);
  }

  $xpath = new DOMXPath($dom);

  // Firmware:
  $query = '//flat-profile/router-configuration/About_Product/Firmware_Version';
  $entries_firmware = $xpath->query($query);
  foreach ($entries_firmware as $entry)
  {
    $asset['firmware'] = $entry->nodeValue;
  }

  // Serial:
  $query = '//flat-profile/router-configuration/About_Product/Serial_Number';
  $entries_serial = $xpath->query($query);
  foreach ($entries_serial as $entry)
  {
    $asset['serial'] = $entry->nodeValue;
  }

  // Model:
  $query = '//flat-profile/router-configuration/About_Product/Model';
  $entries_model = $xpath->query($query);
  foreach ($entries_model as $entry)
  {
    $asset['model'] = $entry->nodeValue;
  }

  /*
   <About_Product>
  -- <Firmware_Version>1.01.19 (004)</Firmware_Version>
  -- <Model>SRP521W, FE WAN, 802.11n ETSI, 2FXS/1FXO</Model>
  <Product_ID>SRP521W-K9-G5</Product_ID>
  <Version_ID>V01</Version_ID>
  -- <Serial_Number>CBT1443072D</Serial_Number>
  </About_Product>
  </router-configuration>
  </flat-profile>
  */

  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
}
catch(Exception $e)
{
  sms_log_error("$line: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return 0;

?>