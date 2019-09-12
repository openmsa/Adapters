<?php

  /**
   * Create Feb 04, 2014
   * Get the routers/switchs CISCO via the ENC
   */

  require_once 'smserror/sms_error.php';
  require_once 'smsd/sms_common.php';
  require_once load_once('enc', 'enc_connect.php');
  require_once "$db_objects";

  try
  {

    $asset = array();
    $asset_attributes = array();

    // HTTP connection to the ENC Controller
    $enc_asset_url = 'http://10.30.18.25:8080/DataAccessService/network-device/cd0b0028-bfb0-4e09-95e1-3ed968c9a0e8';
    $asset_json = file_get_contents($enc_asset_url);

    // get the device model
    $pattern = '@,"family":"(?<model>.[0-9A-Z/]+)",@';
    if(preg_match($pattern, $asset_json, $matches) > 0)
    {
      $asset['model'] = trim($matches['model']);
     // echo $asset['model'];
    }

    // get the device Memory Size
    $pattern = '@,"memorySize":"(?<memory>.[0-9A-Z/]+)",@';
    if(preg_match($pattern, $asset_json, $matches) > 0)
    {
      $asset['memory'] = trim($matches['memory']);
      //echo $asset['memory'];
    }

    // get the device Firmware Version
    $pattern = '@,"softwareVersion":"(?<version>.[0-9A-Z.()]+)",@';
    if(preg_match($pattern, $asset_json, $matches) > 0)
    {
      $asset['version'] = trim($matches['version']);
      //echo $asset['version'];
    }

    // get the device Firmware Name
    $pattern = '@,"imageName":"(?<firmware>.[0-9a-zA-Z._/-]+)",@';
    if(preg_match($pattern, $asset_json, $matches) > 0)
    {
      $asset['firmware'] = trim($matches['firmware']);
      //echo $asset['firmware'];
    }

    // get the device Serial Number
    $pattern = '@,"serialNumber":"(?<serial>.[0-9A-Z]+)",@';
    if(preg_match($pattern, $asset_json, $matches) > 0)
    {
      $asset['serial'] = trim($matches['serial']);
      //echo $asset['serial'];
    }

    foreach ($asset_attributes as $name => $value)
    {
      $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
      if ($ret !== 0)
      {
        throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
      }
    }

    $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
    if ($ret !== 0)
    {
      debug_dump($asset, "Asset failed:\n");
      throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
    }

    //echo '---------------------------------------------\n';

  }

  catch (Exception | Error $e)
  {
  	sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  	return $e->getCode();
  }

  return SMS_OK;

?>