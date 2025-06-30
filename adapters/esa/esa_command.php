<?php
/*
 * Version: $Id$
 * Created: Apr 28, 2011
 * Available global variables
 *    $sms_csp            pointer to csp context to send response to user
 *    $sdid               pointer the sdid
 *    $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *    $SMS_RETURN_BUF     string buffer containing the result
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_command.php';

require_once load_once('esa', 'esa_configuration.php');
require_once load_once('esa', 'adaptor.php');

class esa_command extends generic_command
{

  function __construct() {
    parent::__construct ();
    $this->parsed_objects = array ();
  }

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
   * @param object $json_params			JSON parameters of the command
   * @param domElement $element			XML DOM element of the definition of the command
   */
  function eval_IMPORT()
  {
    //global $sms_sd_ctx;
    global $SMS_RETURN_BUF;
    global $sdid;

    $ret = sd_connect();
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    if (!empty($this->parser_list))
    {
      $objects = array();
      $parser_list = array();

      foreach ($this->parser_list as $parser)
      {
        $op_eval = $parser->evaluate_internal('IMPORT', 'operation');
        $cmd = $op_eval;
        // Group parsers into evaluated operations
        $parser_list[$cmd][] = $parser;
      }

      foreach ($parser_list as $sub_parsers)
      {
        // Run evaluated operation
        // Get the conf on the router
        $conf = new esa_configuration($sdid);
        $running_conf = $conf->get_running_conf();
        $XMLConfig = new SimpleXMLElement(preg_replace('/xmlns="[^"]+"/', '', $running_conf));
        //debug_dump($sms_sd_ctx->get_raw_xml());
        // Apply concerned parsers
        foreach ($sub_parsers as $parser)
        {
          $parser->parse($XMLConfig, $objects);
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
  function eval_CREATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->create_list as $create)
    {
      $conf = trim($create->evaluate_operation());
      $xpath = trim($create->evaluate_xpath());
      $conf .= '&xpath=' . trim($xpath);
      $conf .= '&element=';
      $xml_conf = trim($create->evaluate_xml());
      $conf .= "{$xml_conf}\n";
      $this->configuration .= "{$conf}\n";
      $SMS_RETURN_BUF .= "{$conf}\n";
    }
    return SMS_OK;
  }

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_device_CREATE($params)
  {
    debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

    $ret = sd_apply_conf($this->configuration, true);

    return $ret;
  }

  /*
   * #####################################################################################
   * UPDATE
   * #####################################################################################
   */
  function eval_UPDATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->update_list as $update)
    {
      $conf = trim($update->evaluate_operation());
      $xpath = trim($update->evaluate_xpath());
      $conf .= '&xpath=' . trim($xpath);
      $conf .= '&element=';
      $xml_conf = trim($update->evaluate_xml());
      $conf .= "{$xml_conf}\n";
      $this->configuration .= "{$conf}\n";
      $SMS_RETURN_BUF .= "{$conf}\n";
    }
    return SMS_OK;
  }

  /**
   * Apply updated object to device and if OK add object to the database.
   */
  function apply_device_UPDATE($params)
  {
    debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

    $ret = sd_apply_conf($this->configuration, true);

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

    foreach ($this->delete_list as $delete)
    {
      $conf = trim($delete->evaluate_operation());
      $xpath = trim($delete->evaluate_xpath());
      $conf .= '&xpath=' . trim($xpath);
      $conf .= '&element=';
      $xml_conf = trim($delete->evaluate_xml());
      $conf_lines = preg_split("/\n/", $xml_conf);
      foreach ($conf_lines as $conf_line)
      {
        $conf .= trim($conf_line);
      }
      $this->configuration .= "{$conf}\n";
      $SMS_RETURN_BUF .= "{$conf}\n";
    }
    return SMS_OK;
  }

  /**
   * Apply deleted object to device and if OK add object to the database.
   */
  function apply_device_DELETE($params)
  {
    debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

    $ret = sd_apply_conf($this->configuration, true);

    return $ret;
  }
}

?>
