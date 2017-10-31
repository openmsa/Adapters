<?php
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('a10_thunder', 'common.php');
require_once load_once('a10_thunder', 'adaptor.php');
require_once load_once('a10_thunder', 'a10_thunder_apply_conf.php');
// require_once load_once('a10_thunder', 'a10_thunder_apply_restore_conf.php');

require_once "$db_objects";

class a10_thunder_configuration {
    var $conf_path; // Path for previous stored configuration files
    var $sdid; // ID of the SD to update
    var $running_conf; // Current configuration of the router
    var $profile_list; // List of managed profiles
    var $previous_conf_list; // Previous generated configuration loaded from files
    var $conf_list; // Current generated configuration waiting to be saved
    var $addon_list; // List of managed addon cards
    var $fmc_repo; // repository path without trailing /
    var $sd;
    var $is_ztd;

    // ------------------------------------------------------------------------------------------------
    /**
     * Constructor
     */
    function __construct($sdid, $is_provisionning = false) {
        $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
        $this->sdid = $sdid;
        $this->conf_pflid = 0;
        $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
        $net = get_network_profile();
        $this->sd = &$net->SD;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     * Get running configuration from the router
     */
    function get_running_conf() {
        global $sms_sd_ctx;
        $SMS_OUTPUT_BUF = '';
        
        // Run the CLI Cmd
        $cmd = "show running-config";
        $SMS_OUTPUT_BUF .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd, "#");

        $config_string = "";

        if ($SMS_OUTPUT_BUF != '') {
            $line = get_one_line($SMS_OUTPUT_BUF);
            while ($line !== false) {
                if (strpos($line, $sms_sd_ctx->getPrompt()) === false && strpos($line, $cmd) === false) {
                    $config_string .= $line . "\n";
                }

                $line = get_one_line($SMS_OUTPUT_BUF);
            }
        }

        $this->running_conf = $config_string;
        return $this->running_conf;
    }

    // ------------------------------------------------------------------------------------------------
    function get_staging_conf() {
        $staging_conf = PATTERNIZETEMPLATE("staging_conf.tpl");
        return $staging_conf;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     * Generate the general pre-configuration
     *
     * @param $configuration configuration
     *            buffer to fill
     */
    function generate_pre_conf(&$configuration) {
        $configuration .= "!PRE CONFIG\n";
        get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_CONFIG', 'Configuration');
        return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     * Generate a full configuration
     * Uses the previous conf if present to perform deltas
     */
    function generate(&$configuration, $use_running = false) {
        $configuration .= "! CONFIGURATION GOES HERE\n";
        $configuration .= '';
        return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     * Generate the general post-configuration
     *
     * @param $configuration configuration
     *            buffer to fill
     */
    function generate_post_conf(&$configuration) {
        $configuration .= "!POST CONFIG\n";
        get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_CONFIG', 'Configuration');
        return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     */
    function build_conf(&$generated_configuration) {
        $ret = $this->generate_pre_conf($generated_configuration);
        if ($ret !== SMS_OK) {
            return $ret;
        }
        $ret = $this->generate($generated_configuration);
        if ($ret !== SMS_OK) {
            return $ret;
        }

        $ret = $this->generate_post_conf($generated_configuration);
        if ($ret !== SMS_OK) {
            return $ret;
        }

        return SMS_OK;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     */
    function update_conf() {
        $ret = $this->build_conf($generated_configuration);

        if (! empty($generated_configuration)) {
            $ret = a10_thunder_apply_conf($generated_configuration);
        }

        return $ret;
    }

    // ------------------------------------------------------------------------------------------------
    /**
     */
    function provisioning() {
        return $this->update_conf();
    }

    // ------------------------------------------------------------------------------------------------
    function reboot($event, $params = '') {
        status_progress('Reloading device', $event);
        func_reboot();
        // sleep(30);
        $ret = wait_for_device_up($this->sd->SD_IP_CONFIG);
        if ($ret != SMS_OK) {
            return $ret;
        }
        status_progress('Connecting to the device', $event);

        $loop = 20;
        while ($loop > 0) {
            sleep(10); // wait for ssh to come up
            $ret = a10_thunder_connect();
            if ($ret == SMS_OK) {
                break;
            }
            $loop --;
        }

        return $ret;
    }
}

?>
