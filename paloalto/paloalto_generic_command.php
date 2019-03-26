<?php
/*
 * Version: $Id$
* Created: Apr 28, 2011
* Available global variables
*  $sms_csp            pointer to csp context to send response to user
*       $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
*       $SMS_RETURN_BUF     string buffer containing the result
*/
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'generic_command.php');
require_once load_once('smsd', 'cmd_create_xml.php');
require_once load_once('smsd', 'cmd_update_xml.php');
require_once load_once('smsd', 'cmd_delete_xml.php');
require_once load_once('smsd', 'cmd_import_xml.php');

require_once load_once('smsd', 'cmd_read.php');
require_once load_once('smsd', 'cmd_list.php');

require_once load_once('paloalto_generic', 'adaptor.php');
class paloalto_generic_command extends generic_command
{
    public $parser_list;
    public $parsed_objects;
    public $create_list;
    public $delete_list;
    public $list_list;
    public $read_list;
    public $update_list;
    public $configuration;
    public $import_file_list;

    public function __construct()
    {
        parent::__construct();
        $this->parser_list = array();
        $this->create_list = array();
        $this->delete_list = array();
        $this->list_list = array();
        $this->read_list = array();
        $this->update_list = array();
        $this->import_file_list = array();
    }

    /*
     * #####################################################################################
    * IMPORT
    * #####################################################################################
    */

    /*
     * #####################################################################################
    * IMPORT
    * #####################################################################################
    */

    public function decode_IMPORT($object, $json_params, $element)
    {
        $parser = new cmd_import($object, $element, $json_params);
        $this->parser_list[] = &$parser;
    }

    /**
     * IMPORT configuration from router
     * @param object $json_params                 JSON parameters of the command
     * @param domElement $element                 XML DOM element of the definition of the command
     */
    public function eval_IMPORT()
    {
        global $sms_sd_ctx;
        global $SMS_RETURN_BUF;

        try {
            $ret = sd_connect();
            if ($ret != SMS_OK) {
                return $ret;
            }

            if (!empty($this->parser_list)) {
                $objects = array();
                $parser_list = array();

                foreach ($this->parser_list as $parser) {
                    $op_eval = $parser->evaluate_internal('IMPORT', 'operation');
                    $xpath_eval = $parser->evaluate_internal('IMPORT', 'xpath');
                    $cmd = trim($op_eval) . '&xpath=' . urlencode(trim($xpath_eval));
                    // Group parsers into evaluated operations
                    $parser_list[$cmd][] = $parser;
                }

                foreach ($parser_list as $op_eval => $sub_parsers) {
                    // Run evaluated operation
                    $op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);
                    foreach ($op_list as $op) {
                        $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
                        //debug_dump($sms_sd_ctx->get_raw_xml());
                        // Apply concerned parsers
                        foreach ($sub_parsers as $parser) {
                            $parser->parse($running_conf, $objects);
                        }
                    }
                }

                $this->parsed_objects = $objects;

                debug_object_conf($objects);
                $SMS_RETURN_BUF .= json_encode($objects);
            }

            sd_disconnect();
        } catch (Exception $e) {
            return $e->getCode();
        }

        return SMS_OK;
    }

    /*
     * #####################################################################################
    * CREATE
    * #####################################################################################
    */
    public function eval_CREATE()
    {
        global $SMS_RETURN_BUF;

        foreach ($this->create_list as $create) {
            $conf = trim($create->evaluate_operation());
            if (!empty($conf)) {
                $xpath = explode('&', trim($create->evaluate_xpath()), 2);
                $conf .= '&xpath=' . urlencode(trim($xpath[0]));
                if(!empty(trim($xpath[1]))) {
                    $conf .= '&' . trim($xpath[1]);
                }   
                if (!empty(trim($create->evaluate_xml()))) {
                    $conf .= '&element=';
                    $xml_conf = trim($create->evaluate_xml());
                    $conf_lines = preg_split("/\n/", $xml_conf);
                    foreach ($conf_lines as $conf_line) {
                        $conf .= urlencode(trim($conf_line));
                    }
                }
                $this->configuration .= "{$conf}\n";
                $SMS_RETURN_BUF .= "{$conf}\n";
            }
        }
        return SMS_OK;
    }

    /**
     * Apply created object to device and if OK add object to the database.
     */
    public function apply_device_CREATE($params)
    {
        $ret = SMS_OK;
        if (!empty($this->configuration)) {
            debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
            $ret = sd_apply_conf($this->configuration, true);
        }

        return $ret;
    }

    /*
     * #####################################################################################
    * UPDATE
    * #####################################################################################
    */
    public function eval_UPDATE()
    {
        global $SMS_RETURN_BUF;

        foreach ($this->update_list as $update) {
            $conf = trim($update->evaluate_operation());
            if (!empty($conf)) {
                $xpath = explode('&', trim($update->evaluate_xpath()), 2);
                $conf .= '&xpath=' . urlencode(trim($xpath[0]));
                 if(!empty(trim($xpath[1]))) {
                    $conf .= '&' . trim($xpath[1]);
                }
                if (!empty(trim($update->evaluate_xml()))) {
                    $conf .= '&element=';
                    $xml_conf = trim($update->evaluate_xml());
                    $conf_lines = preg_split("/\n/", $xml_conf);
                    foreach ($conf_lines as $conf_line) {
                        $conf .= urlencode(trim($conf_line));
                    }
                }
                $this->configuration .= "{$conf}\n";
                $SMS_RETURN_BUF .= "{$conf}\n";
            }
        }
        return SMS_OK;
    }

    /**
     * Apply updated object to device and if OK add object to the database.
     */
    public function apply_device_UPDATE($params)
    {
        $ret = SMS_OK;
        if (!empty($this->configuration)) {
            debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
            $ret = sd_apply_conf($this->configuration, true);
        }
        return $ret;
    }

    /*
     * #####################################################################################
    * DELETE
    * #####################################################################################
    */
    public function eval_DELETE()
    {
        global $SMS_RETURN_BUF;

        foreach ($this->delete_list as $delete) {
            $conf = trim($delete->evaluate_operation());
            $xpath = trim($delete->evaluate_xpath());
            $conf .= '&xpath=' . urlencode(trim($xpath));
            $this->configuration .= "{$conf}\n";
            $SMS_RETURN_BUF .= "{$conf}\n";
        }
        return SMS_OK;
    }

    /**
     * Apply deleted object to device and if OK add object to the database.
     */
    public function apply_device_DELETE($params)
    {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }
}

?>
