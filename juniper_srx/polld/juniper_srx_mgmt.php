<?php
/*
 * 	Available global variables
*  	$sms_sd_info       sd_info structure
*  	$sdid
*  	$sms_module        module name (for patterns)
*  	$sd_poll_elt       pointer on sd_poll_t structure
*   $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
*/


// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('juniper_srx', 'juniper_srx_connect.php');
require_once "$db_objects";

try
{
	// Connection
	juniper_srx_connect();

	$asset = array();
	$asset_attributes = array();

	// GET ASSETS
	/*
	Hostname: SRX66774
	Model: srx100h
	JUNOS Software Release [11.2R4.3]
	 */
	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show version");

	$show_version_patterns = array(
	    'firmware' => '@JUNOS Software.*\[(?<firmware>[^\]]+)\]@',
	    'model' => '@Model:\s+(?<model>.*)@',
	);

	foreach ($show_version_patterns as $name => $pattern)
	{
	  if (preg_match($pattern, $buffer, $matches) > 0)
	  {
	    $asset[$name] = trim($matches[$name]);
	  }
	}

	// Get Memory asset
	/*
	Routing Engine status:
	Temperature                 47 degrees C / 116 degrees F
	Total memory              1024 MB Max   512 MB used ( 50 percent)
	Control plane memory     560 MB Max   358 MB used ( 64 percent)
	Data plane memory        464 MB Max   158 MB used ( 34 percent)
	*/

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show chassis routing-engine");

	$pattern = '@Total\s+memory\s+(?<memory>\d+\s+\S+)@';
	if(preg_match($pattern, $buffer, $matches) > 0)
	{
	  $asset['memory'] = trim($matches['memory']);
	}

	// Get Serial Number
	// show chassis hardware
	/*
	root@SRX66774> show chassis hardware detail
	Hardware inventory:
	Item             Version  Part number  Serial number     Description
	Chassis                                AU0813AF0450      SRX100H
	Routing Engine   REV 20   750-021773   AT0813AF0450      RE-SRX100H
	  da0     999 MB  ST72682                                Nand Flash
	  usb0 (addr 1)  DWC OTG root hub 0    vendor 0x0000     uhub0
	  usb0 (addr 2)  product 0x005a 90     vendor 0x0409     uhub1
	  usb0 (addr 3)  ST72682  High Speed Mode 64218 STMicroelectronics umass0
	FPC 0                                                    FPC
	  PIC 0                                                  8x FE Base PIC
	Power Supply 0
	 */

	$buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "show chassis hardware");

	$show_chassis_hardware_patterns = array(
			'serial' => '@Chassis\s+(?<serial>\S+)@',
	);

	foreach ($show_chassis_hardware_patterns as $name => $pattern)
	{
		if (preg_match($pattern, $buffer, $matches) > 0)
		{
			$asset[$name] = trim($matches[$name]);
		}
	}

	$ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
	if ($ret !== 0)
	{
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
	}

	juniper_srx_disconnect();

}

catch (Exception | Error $e)
{
	juniper_srx_disconnect();
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>

