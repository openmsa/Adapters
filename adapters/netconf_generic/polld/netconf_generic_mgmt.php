<?php
/*
 * Available global variables
 * $sms_sd_info sd_info structure
 * $sdid
 * $sms_module module name (for patterns)
 * $sd_poll_elt pointer on sd_poll_t structure
 * $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once ( 'netconf_generic', 'common.php' );
require_once load_once ( 'netconf_generic', 'netconf_generic_connect.php' );
require_once "$db_objects";

try {
	// Connection
	netconf_generic_connect();
	$asset = array ();
	$asset_attributes = array ();

	$capability_id = 0;

	$xml = simplexml_load_string(preg_replace('/xmlns="[^"]+"/', '', xml_remove_endcomment($sms_sd_ctx->get_device_capabilities())));
	$matched_capabilities = $xml->xpath("//capability");
	if ( $matched_capabilities === FALSE )
	{
		echo "xpath (//capability) failed\n";
	}
	else
	{
		echo "xpath (//capability) succeed\n";
		while(list( , $node) = each($matched_capabilities ))
		{
			$capability_id += 1;
			echo "a capability found: [$node]\n";
			$ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, "Capability $capability_id", $node);
		}
	}

	$buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "<rpc><get-software-information format='text'/></rpc> \n", ']]>]]>' );

	$show_version_patterns = array (
			'firmware' => '@JUNOS Software.*\[(?<firmware>[^\]]+)\]@',
			'model' => '@Model:\s+(?<model>.*)@'
	);

	foreach ( $show_version_patterns as $name => $pattern ) {
		if (preg_match ( $pattern, $buffer, $matches ) > 0) {
			$asset [$name] = trim ( $matches [$name] );
		}
	}

	// Get Memory asset
	/*
	 * <rpc-reply xmlns="urn:ietf:params:xml:ns:netconf:base:1.0" xmlns:junos="http://xml.juniper.net/junos/11.4R7/junos">
	 * <output>
	 * Routing Engine status:
	 * Temperature 36 degrees C / 96 degrees F
	 * CPU temperature 36 degrees C / 96 degrees F
	 * Total memory 1024 MB Max 727 MB used ( 71 percent)
	 * Control plane memory 560 MB Max 414 MB used ( 74 percent)
	 * Data plane memory 464 MB Max 316 MB used ( 68 percent)
	 * ...
	 * ......
	 * </output>
	 * </rpc-reply>
	 * ]]>]]>
	 */

	$buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "<rpc><get-route-engine-information format='text'/></rpc> \n", ']]>]]>' );

	$pattern = '@Total\s+memory\s+(?<memory>\d+\s+\S+)@';
	if (preg_match ( $pattern, $buffer, $matches ) > 0) {
		$asset ['memory'] = trim ( $matches ['memory'] );
	}

	// Get Serial Number
	// show chassis hardware<rpc-reply xmlns="urn:ietf:params:xml:ns:netconf:base:1.0" xmlns:junos="http://xml.juniper.net/junos/11.4R7/junos">
	/*
	 * <output>
	 * Hardware inventory:
	 * Item Version Part number Serial number Description
	 * Chassis AG4809AA0470 SRX240H
	 * Routing Engine REV 38 750-021793 AAAY9436 RE-SRX240H
	 * FPC 0 FPC
	 * PIC 0 16x GE Base PIC
	 * Power Supply 0
	 * </output>
	 * </rpc-reply>
	 * ]]>]]>
	 */

	$buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "<rpc><get-chassis-inventory format='text'/></rpc> \n", ']]>]]>' );

	$show_chassis_hardware_patterns = array (
			'serial' => '@Chassis\s+(?<serial>\S+)@'
	);

	foreach ( $show_chassis_hardware_patterns as $name => $pattern ) {
		if (preg_match ( $pattern, $buffer, $matches ) > 0) {
			$asset [$name] = trim ( $matches [$name] );
		}
	}

	$ret = sms_polld_set_asset_in_sd ( $sd_poll_elt, $asset );
	if ($ret !== 0) {
		debug_dump ( $asset, "Asset failed:\n" );
		throw new SmsException ( " sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED );
	}

	netconf_generic_disconnect ();
}

catch ( Exception | Error $e ) {
	netconf_generic_disconnect ();
	sms_log_error ( "Exception occur: " . $e->getMessage () . "\n" );
	return $e->getCode ();
}

return SMS_OK;

?>

