<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_command.php';

require_once load_once('a10_thunder', 'adaptor.php');

class a10_thunder_command extends generic_command {

    function __construct() {
        parent::__construct ();
        $this->parsed_objects = array ();
    }

    /*
     * #####################################################################################
     * IMPORT
     * #####################################################################################
     */

    /**
     * IMPORT configuration from router
     *
     * @param object $json_params
     *            parameters of the command
     * @param domElement $element
     *            DOM element of the definition of the command
     */
    function eval_IMPORT() {
        global $sms_sd_ctx;
        global $SMS_RETURN_BUF;

        $ret = sd_connect();
        if ($ret != SMS_OK) {
            return $ret;
        }
        if (! empty($this->parser_list)) {
            $objects = array();
            // One operation groups several parsers
            foreach ($this->parser_list as $operation => $parsers) {
                $sub_list = array();
                foreach ($parsers as $parser) {
                    $op_eval = $parser->eval_operation();
                    // Group parsers into evaluated operations
                    $sub_list["$op_eval"][] = $parser;
                }

                foreach ($sub_list as $op_eval => $sub_parsers) {
                    // Run evaluated operation
                    $running_conf = '';
                    $op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);
                    foreach ($op_list as $op) {
                        $running_conf .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
                    }
                    // Apply concerned parsers
                    foreach ($sub_parsers as $parser) {
                        $parser->parse($running_conf, $objects);
                    }
                }
            }

            $this->parsed_objects = array_replace_recursive($this->parsed_objects, $objects);

            debug_object_conf($this->parsed_objects);
            $SMS_RETURN_BUF = object_to_json($this->parsed_objects);
        }

        sd_disconnect();

        return SMS_OK;
    }

    /*
     * #####################################################################################
     * CREATE
     * #####################################################################################
     */

    /**
     * Apply created object to device and if OK add object to the database.
     */
    function apply_device_CREATE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    /*
     * #####################################################################################
     * UPDATE
     * #####################################################################################
     */

    /**
     * Apply updated object to device and if OK add object to the database.
     */
    function apply_device_UPDATE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    /*
     * #####################################################################################
     * DELETE
     * #####################################################################################
     */
    function eval_DELETE() {
        global $SMS_RETURN_BUF;

        foreach ($this->delete_list as $delete) {
            $conf = $delete->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";

        return SMS_OK;
    }

    /**
     * Apply deleted object to device and if OK add object to the database.
     */
    function apply_device_DELETE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    function eval_CREATE() {
        global $SMS_RETURN_BUF;

        foreach ($this->create_list as $create) {
            $conf = $create->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";
        return SMS_OK;
    }

    function eval_UPDATE() {
        global $SMS_RETURN_BUF;

        foreach ($this->update_list as $update) {
            $conf = $update->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";

        return SMS_OK;
    }
}

?>
