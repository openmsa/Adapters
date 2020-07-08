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

require_once load_once('smsd', 'generic_command.php');
require_once load_once('smsd', 'cmd_create_xml.php');
require_once load_once('smsd', 'cmd_update_xml.php');
require_once load_once('smsd', 'cmd_delete_xml.php');
require_once load_once('smsd', 'cmd_import_xml.php');
require_once load_once('smsd', 'cmd_list.php');

require_once load_once('nfvo_generic', 'adaptor.php');

class nfvo_generic_command extends generic_command
{

	var $parser_list;

	var $parsed_objects;

	var $create_list;

	var $delete_list;

	var $list_list;

	var $read_list;

	var $update_list;

	var $configuration;

	var $import_file_list;

	function __construct()
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
	function decode_IMPORT($object, $json_params, $element)
	{
		$parser = new cmd_import($object, $element, $json_params);
		$this->parser_list[] = &$parser;
	}

	/**
	 * IMPORT configuration from router
	 *
	 * @param object $json_paramsJSON
	 *        	parameters of the command
	 * @param domElement $elementXML
	 *        	DOM element of the definition of the command
	 */
	function eval_IMPORT()
	{
		global $sms_sd_ctx;
		global $SMS_RETURN_BUF;

		try {
			$ret = sd_connect();
			if ($ret != SMS_OK) {
				return $ret;
			}

			if (! empty($this->parser_list)) {
				$objects = array();
				$parser_list = array();

				foreach ($this->parser_list as $parser) {
					$op_eval = $parser->evaluate_internal('IMPORT', 'operation');
					$xpath_eval = $parser->evaluate_internal('IMPORT', 'xpath');
					$path_list = preg_split('@##@', $xpath_eval, 0, PREG_SPLIT_NO_EMPTY);
					foreach ($path_list as $xpth) {
						$cmd = 'GET#' . trim($op_eval) . '#' . trim($xpth);
						$parser_list[$cmd][] = $parser;
					}
				}

				foreach ($parser_list as $op_eval => $sub_parsers) {
					// Run evaluated operation
					$op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);

					foreach ($op_list as $op) {

						$running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
						// Apply concerned parsers
						foreach ($sub_parsers as $parser) {
							$parser->parse($running_conf, $objects);
						}
					}
				}

				$this->parsed_objects = $objects;
				$SMS_RETURN_BUF .= object_to_json($objects);
			}

			sd_disconnect();
		} catch (Exception | Error $e) {
			return $e->getCode();
		}

		return SMS_OK;
	}

	/*
	 * #####################################################################################
	 * CREATE
	 * This method can call multiple rest APIs while creating one object, the object definition has to be structured in following way:
	 * The Endpoint section: It has to have all endpoints that the Object is going to use separated by '##', the fields should start with the
	 * HTTP method name followed by a '#' and then the endpoint (Example: POST#nova)
	 * The XPath section: This section has to have the same number of XPaths separated by '##' as many as Endpoints
	 * The config section: This section can have one or more (upto as many as Endpoints/XPaths), each part of config is separated by '##'.
	 * Please remember only the tailing parts can be ignored in config section
	 * #####################################################################################
	 */
	function eval_CREATE()
	{
		global $SMS_RETURN_BUF;

		foreach ($this->create_list as $create) {
			$endpoint_str = trim($create->evaluate_operation());
			$endpoints = explode("##", $endpoint_str);
			$xpath_str = trim($create->evaluate_xpath());
			$xpaths = explode("##", $xpath_str);

			$xml_conf_str = trim($create->evaluate_xml());
			$xml_conf_str = str_replace("\n", '', $xml_conf_str);

			$xml_configs = explode("##", $xml_conf_str);

			if (! empty($endpoint_str)) {
				if (count($xpaths) != count($endpoints)) {
					throw new SmsException("End points are not as many as Xpaths");
				} else {
					$i = 0;
					foreach ($xml_configs as $xml_conf) {
						if (! empty($xml_conf)) {
							$conf = $endpoints[$i];
							$conf .= '#' . $xpaths[$i];
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

		return SMS_OK;
	}

	/**
	 * Apply created object to device and if OK add object to the database.
	 */
	function apply_device_CREATE($params)
	{
		$ret = SMS_OK;
		if (! empty($this->configuration)) {
			debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
			$ret = sd_apply_conf($this->configuration, true);
		}

		return $ret;
	}

	/*
	 * #####################################################################################
	 * UPDATE
	 * This method can call multiple rest APIs while creating one object, the object definition has to be structured in following way:
	 * The Endpoint section: It has to have all endpoints that the Object is going to use separated by '##', the fields should start with the
	 * HTTP method name followed by a '#' and then the endpoint (Example: POST#nova)
	 * The XPath section: This section has to have the same number of XPaths separated by '##' as many as Endpoints
	 * The config section: This section can have one or more (upto as many as Endpoints/XPaths), each part of config is separated by '##'.
	 * Please remember only the tailing parts can be ignored in config section
	 * #####################################################################################
	 */
	function eval_UPDATE()
	{
		global $SMS_RETURN_BUF;

		foreach ($this->update_list as $update) {

			$endpoint_str = trim($update->evaluate_operation());
			$endpoints = explode("##", $endpoint_str);
			$xpath_str = trim($update->evaluate_xpath());
			$xpath_str = str_replace("\xE2\x80\x8B", "", $xpath_str);
			$xpaths = explode("##", $xpath_str);

			$xml_conf_str = trim($update->evaluate_xml());
			$xml_conf_str = str_replace("\n", '', $xml_conf_str);

			$xml_configs = explode("##", $xml_conf_str);
			if (! empty($endpoint_str)) {

				if (count($xpaths) != count($endpoints)) {
					throw new SmsException("End points are not as many as Xpaths");
				} else {
					$i = 0;
					foreach ($xml_configs as $xml_conf) {
						if (! empty($xml_conf)) {
							$conf = $endpoints[$i];
							$conf .= '#' . $xpaths[$i];
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
		return SMS_OK;
	}

	/**
	 * Apply updated object to device and if OK add object to the database.
	 */
	function apply_device_UPDATE($params)
	{
		$ret = SMS_OK;
		if (! empty($this->configuration)) {
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
	function eval_DELETE()
	{
		global $SMS_RETURN_BUF;

		foreach ($this->delete_list as $delete) {
			$endpoint = trim($delete->evaluate_operation());
			if (! empty($endpoint)) {
				$conf = 'DELETE#' . $endpoint;
				$xpath = trim($delete->evaluate_xpath());
				$xpath = str_replace("\xE2\x80\x8B", "", $xpath);
				$conf .= '#' . $xpath;
				$this->configuration .= "{$conf}\n";
				$SMS_RETURN_BUF .= "{$conf}\n";
			}
		}

		return SMS_OK;
	}

	/**
	 * Apply deleted object to device and if OK add object to the database.
	 */
	function apply_device_DELETE($params)
	{
		debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
		return sd_apply_conf($this->configuration, true);
	}

	/*
	 * #####################################################################################
	 * LIST
	 * #####################################################################################
	 */
	function eval_LIST()
	{
		global $SMS_RETURN_BUF;

		foreach ($this->list_list as $list) {
			$conf = $list->evaluate();
			$this->configuration .= $conf;
			$SMS_RETURN_BUF .= $conf;
		}
		return SMS_OK;
	}
}

