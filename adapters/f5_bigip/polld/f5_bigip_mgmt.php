<?php
// Asset management

/*
 * Available global variables
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $sms_sd_info sd_info structure
 * $sdid
 * $sms_module module name (for patterns)
 * $sd_poll_elt pointer on sd_poll_t structure
 */
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once ( 'f5_bigip', 'f5_bigip_connect.php' );
require_once "$db_objects";

try {
    // Connection
    f5_bigip_connect ();

    $asset = array ();
    $asset_attributes = array ();
    // echo "DEBUG BIG-IP poll script start\n";

    /*
     * config # tmsh show sys version
     *
     * Sys::Version
     * Main Package
     * Product BIG-IP
     * Version 12.0.0
     * Build 0.0.606
     * Edition Final
     * Date Fri Aug 21 13:29:22 PDT 2015
     *
     */
    $buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "tmsh show sys version" ,"#");
    //$sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, "#" );
    $show_version_patterns = array (
            'firmware' => '@[^:]Version\s+(?<firmware>\S+)@',
            'model' => '@Product\s+(?<model>\S+)@'
    );
    foreach ( $show_version_patterns as $name => $pattern ) {
        if (preg_match ( $pattern, $buffer, $matches ) > 0) {
            $asset [$name] = trim ( $matches [$name] );
        }
    }

    // Get Cpu asset
    /*
     * config # tmsh show sys hardware | grep cpus -A 3
     * Name cpus
     * Type base-board
     * Model Intel Xeon E312xx (Sandy Bridge)
     * Parameters -- --
     */
    $buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "tmsh show sys hardware | grep cpus -A 3" ,"#");
    //$sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, "#" );
    $show_hardware_patterns = array (
            'cpu' => '@Model\s+(?<cpu>.+)@'
    );
    foreach ( $show_hardware_patterns as $name => $pattern ) {
        if (preg_match ( $pattern, $buffer, $matches ) > 0) {
            $asset [$name] = trim ( $matches [$name] );
        }
    }

    // Get Serial asset
    /*
     * config # tmsh show sys hardware | grep 'Chassis Serial'
     * Chassis Serial 3efs4c1a-h5aw-72da-3824sf763k81
     */
    $buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "tmsh show sys hardware | grep 'Chassis Serial'" , "#");
    //$sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, "#" );
    $pattern = '@Chassis\s+Serial\s+(?<serial>\S+)@';
    if (preg_match ( $pattern, $buffer, $matches ) > 0) {
        $asset ['serial'] = trim ( $matches ['serial'] );
    }

    // Get Memory asset
    /*
     * config # tmsh show sys memory | grep Total: -A 1
     * Total: 0
     * Total 3.8G
     */
    $buffer = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "tmsh show sys memory | grep Total: -A 1" ,"#");
    //$sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, "#" );
    $pattern = '@Total\s+(?<memory>\S+)@';
    if (preg_match ( $pattern, $buffer, $matches ) > 0) {
        $asset ['memory'] = trim ( $matches ['memory'] );
    }

    debug_dump ( $asset, "asset:\n" );

    // Set standard information
    $ret = sms_polld_set_asset_in_sd ( $sd_poll_elt, $asset );
    if ($ret !== 0) {
        debug_dump ( $asset, "Asset failed:\n" );
        throw new SmsException ( " sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED );
    }

    // Set extended information
    foreach ( $asset_attributes as $name => $value ) {
        $ret = sms_sd_set_asset_attribute ( $sd_poll_elt, 1, $name, $value );
        if ($ret !== 0) {
            throw new SmsException ( " sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED );
        }
    }

    f5_bigip_disconnect ();
}

catch ( Exception $e ) {
    f5_bigip_disconnect ();
    sms_log_error ( "Exception occur: " . $e->getMessage () . "\n" );
    return $e->getCode ();
}

return SMS_OK;

?>
