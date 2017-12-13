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
require_once load_once('a10_thunder', 'a10_thunder_connect.php');
require_once "$db_objects";

try {
    // Connection
    a10_thunder_connect();

    $asset = array();
    $asset_attributes = array();

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show hardware");
    //$sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, "#");
    $line = get_one_line($buffer);
    $line = get_one_line($buffer);
    $asset['model'] = $line;
    // 'model' => '@Product\s+(?<model>\S+)@'


   $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show version");
    //$sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, "#");
    $show_version_patterns = array(
        'firmware' => '@(?<firmware>version \S+, build \S+) \(@'
    );
    foreach ($show_version_patterns as $name => $pattern) {
        if (preg_match($pattern, $buffer, $matches) > 0) {
            $asset[$name] = trim($matches[$name]);
        }
    }

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "show hardware");
    //$sms_sd_ctx->expect(__FILE__ . ':' . __LINE__, "#");
    $show_hardware_patterns = array(
        'cpu' => '@CPU\s+:\s+(?<cpu>.+)@',
        'serial' => '@Serial\s+No\s+:\s+(?<serial>\S+)@',
        'memory' => '@Total\s+System\s+Memory\s+(?<memory>[0-9]+ \S+),@'
    );
    foreach ($show_hardware_patterns as $name => $pattern) {
        if (preg_match($pattern, $buffer, $matches) > 0) {
            $asset[$name] = trim($matches[$name]);
        }
    }

    debug_dump($asset, "asset:\n");

    // Set standard information
    $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
    if ($ret !== 0) {
        debug_dump($asset, "Asset failed:\n");
        throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
    }

    // Set extended information
    foreach ($asset_attributes as $name => $value) {
        $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
        if ($ret !== 0) {
            throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
        }
    }

    a10_thunder_disconnect();
}

catch (Exception $e) {
    a10_thunder_disconnect();
    sms_log_error("Exception occur: " . $e->getMessage() . "\n");
    return $e->getCode();
}

return SMS_OK;

?>
