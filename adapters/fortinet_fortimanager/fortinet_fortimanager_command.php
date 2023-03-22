<?php
/*
 * Version: $Id$
 * Created: Apr 28, 2011
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once ( 'smsd', 'generic_command.php' );
require_once load_once ( 'smsd', 'cmd_create_xml.php' );
require_once load_once ( 'smsd', 'cmd_update_xml.php' );
require_once load_once ( 'smsd', 'cmd_delete_xml.php' );
require_once load_once ( 'smsd', 'cmd_import_xml.php' );

require_once load_once ( 'smsd', 'cmd_read.php' );
require_once load_once ( 'smsd', 'cmd_list.php' );

require_once load_once ( 'fortinet_fortimanager', 'adaptor.php' );
class fortinet_fortimanager_command extends generic_command {
        var $parser_list;
        var $parsed_objects;
        var $create_list;
        var $delete_list;
        var $list_list;
        var $read_list;
        var $update_list;
        var $configuration;
        var $import_file_list;
        function __construct() {
                parent::__construct ();
                $this->parser_list = array ();
                $this->create_list = array ();
                $this->delete_list = array ();
                $this->list_list = array ();
                $this->read_list = array ();
                $this->update_list = array ();
                $this->import_file_list = array ();
        }
        function decode_IMPORT($object, $json_params, $element) {
                $parser = new cmd_import ( $object, $element, $json_params );
                $this->parser_list [] = &$parser;
        }

        /**
         * IMPORT configuration from router
         *
         * @param object $json_params
         *              JSON parameters of the command
         * @param domElement $element
         *              XML DOM element of the definition of the command
         */
        function eval_IMPORT() {
                global $sms_sd_ctx;
                global $SMS_RETURN_BUF;

                try {
                        $ret = sd_connect ();
                        if ($ret != SMS_OK) {
                                return $ret;
                        }

                        if (! empty ( $this->parser_list )) {
                                $objects = array ();
                                $parser_list = array ();

                                foreach ( $this->parser_list as $parser ) {
                                        $op_eval = $parser->evaluate_internal ( 'IMPORT', 'operation' );
                                        $xpath_eval = $parser->evaluate_internal ( 'IMPORT', 'xpath' );

                                        if (strlen ( $xpath_eval ) > 0) {
                                                $path_list = preg_split ( '@##@', $xpath_eval, 0, PREG_SPLIT_NO_EMPTY );
                                                foreach ( $path_list as $xpth ) {
                                                        $cmd = trim ( $op_eval ) . "##" . trim ( $xpth );
                                                        $parser_list [$cmd] [] = $parser;
                                                }
                                        } else {
                                                $cmd = trim ( $op_eval );
                                                // Group parsers into evaluated operations
                                                $parser_list [$cmd] [] = $parser;
                                        }
                                }
                                foreach ( $parser_list as $op_eval => $sub_parsers ) {

                                        $running_conf = sendexpectone ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, $op_eval, "" );
                                        foreach ( $sub_parsers as $parser ) {
                                                $parser->parse ( $running_conf, $objects );
                                        }
                                }

                                $this->parsed_objects = $objects;

                                debug_object_conf ( $objects );
                                $SMS_RETURN_BUF .= json_encode ( $objects );
                        }

                        sd_disconnect ();
                } catch ( Exception $e ) {
                        return $e->getCode ();
                }

                return SMS_OK;
        }
        function eval_CREATE() {
                global $SMS_RETURN_BUF;
                echo "eval_CREATE()\n";
                return $this->eval_OPERATON ( $this->create_list );
        }

        /**
         * Apply created object to device and if OK add object to the database.
         */
        function apply_device_CREATE($params) {
                echo "apply_device_CREATE({$params})\n";
                return $this->apply_device_UPDATE ( $params );
        }
        function eval_UPDATE() {
                echo "eval_UPDATE()\n";
                global $SMS_RETURN_BUF;
                return $this->eval_OPERATON ( $this->update_list );
        }

        /**
         * Apply updated object to device and if OK add object to the database.
         */
        function apply_device_UPDATE($params) {
                echo "apply_device_UPDATE({$params})\n";

                $ret = SMS_OK;
                if (! empty ( $this->configuration )) {
                        debug_dump ( $this->configuration, "CONFIGURATION TO SEND TO THE DEVICE" );
                        $ret = sd_apply_conf ( $this->configuration, true );
                }
                return $ret;
        }
        private function eval_OPERATON($list) {
                echo "eval_UPDATE()\n";
                global $SMS_RETURN_BUF;

                foreach ( $list as $name ) {

                        $endpoint_str = trim ( $name->evaluate_operation () );
                        $endpoints = explode ( "##", $endpoint_str );
                        $xpath_str = trim ( $name->evaluate_xpath () );
                        $xpaths = explode ( "##", $xpath_str );

                        $xml_conf_str = trim ( $name->evaluate_xml () );
                        $xml_conf_str = str_replace ( "\n", '', $xml_conf_str );

                        $xml_configs = explode ( "##", $xml_conf_str );
                        if (! empty ( $endpoint_str )) {

                                if (count ( $xpaths ) != count ( $endpoints )) {
                                        throw new SmsException ( "End points are not as many as Xpaths" );
                                } else {
                                        $i = 0;
                                        foreach ( $xml_configs as $xml_conf ) {
                                                if (! empty ( $xml_conf )) {
                                                        $conf = $endpoints [$i];
                                                        $conf .= '#' . $xpaths [$i];
                                                        // separate data with '#'
                                                        $conf .= '#' . $xml_conf;

                                                        $this->configuration .= "{$conf}\n";
                                                        $SMS_RETURN_BUF .= "{$conf}\n";
                                                }
                                                $i += 1;
                                        }
                                }
                        }
                }
                debug_dump ( $SMS_RETURN_BUF, "SMS_RETURN_BUF\n" );
                return SMS_OK;
        }

        function eval_DELETE() {
                global $SMS_RETURN_BUF;
                echo "eval_DELETE()\n";
                return $this->eval_OPERATON ( $this->delete_list );
        }

        /**
         * Apply deleted object to device and if OK add object to the database.
         */
        function apply_device_DELETE($params) {
                debug_dump ( $this->configuration, "CONFIGURATION TO SEND TO THE DEVICE" );

                $ret = sd_apply_conf ( $this->configuration, true );

                return $ret;
        }
}

?>