<?php
/*
 *  Available global variables
 *   $sms_sd_info       sd_info structure
 *   $sdid
 *   $sms_module        module name (for patterns)
 *   $sd_poll_elt       pointer on sd_poll_t structure
 *   $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('vmware_vsphere', 'vmware_vsphere_connect.php');
require_once "$db_objects";

try
{
  // Connection
  vmware_vsphere_connect();

  $asset = array();
  $asset_attributes = array();
  
  /**
   * GET https://{server}/rest/appliance/system/version
   * 
   *{
    	"value": {
        	"build": "string",
        	"install_time": "string",
        	"product": "string",
        	"releasedate": "string",
        	"summary": "string",
        	"type": "string",
        	"version": "string"
    	}
	} 
   */
  
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "GET#appliance#system/version");
  $buffer = $sms_sd_ctx->get_raw_json();
  $buffer = json_decode($buffer, true);

  foreach ($buffer['value'] as $key => $value) {

    echo "$key => $value\n";
    $asset[$key] = $value;
    $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $key, $value);
  }
  
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  vmware_vsphere_disconnect();
}

catch (Exception | Error $e)
{
  vmware_vsphere_disconnect();
  debug_dump($asset, "Asset failed:\n");
  throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
}

return SMS_OK;

?>