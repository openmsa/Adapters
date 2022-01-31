<?php
/*
 * Version: $Id$
 * Created: Apr 28, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 * 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 * 	$SMS_RETURN_BUF     string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'generic_command.php');

require_once load_once('cisco_isr', 'adaptor.php');

class cisco_isr_command extends generic_command
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
  var $iba_parser_list;
  var $iba_parsed_objects;
  var $iba_create_list;
  var $iba_configuration;
  var $iba_delete_list;
  var $iba_update_list;

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
    $this->iba_parser_list = array();
    $this->iba_create_list = array();
    $this->iba_delete_list = array();
    $this->iba_update_list = array();
  }

  /*
   * #####################################################################################
   * IMPORT
   * #####################################################################################
   */

  /**
   * IMPORT configuration from router
   * @param object $json_params			JSON parameters of the command
   * @param domElement $element			XML DOM element of the definition of the command
   */
  function eval_IMPORT()
  {
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    try
    {
      $ret = sd_connect();
      if ($ret != SMS_OK)
      {
        return $ret;
      }

      if (!empty($this->parser_list))
      {
        $objects = array();
        // One operation groups several parsers
        foreach ($this->parser_list as $operation => $parsers)
        {
          $sub_list = array();
          foreach ($parsers as $parser)
          {
            $op_eval = $parser->eval_operation();
            // Group parsers into evaluated operations
            $sub_list["$op_eval"][] = $parser;
          }

          foreach ($sub_list as $op_eval => $sub_parsers)
          {
            // Run evaluated operation
            $running_conf = '';
            $op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);
            foreach ($op_list as $op)
            {
              if (strpos($op, '#') === 0)
              {
                $op = substr($op, 1);
                $running_conf .= "$op\n";
                //echo "#############COMMAND : ".$op."\n";
              }
              else
              {
                $running_conf .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
              }
            }
            // Apply concerned parsers
            foreach ($sub_parsers as $parser)
            {
              $parser->parse($running_conf, $objects);
            }
          }
        }

        $this->parsed_objects = $objects;

        debug_object_conf($objects);
        $SMS_RETURN_BUF .= object_to_json($objects);
      }

      sd_disconnect();
    }
    catch (Exception | Error $e)
    {
      return $e->getCode();
    }

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

  /**
   * Apply deleted object to device and if OK add object to the database.
   */
  function apply_device_DELETE($params)
  {
    debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

    $ret = sd_apply_conf($this->configuration, true);

    return $ret;
  }

  /*
   * #####################################################################################
   * IBA_IMPORT
   * #####################################################################################
   */
  function decode_IBA_IMPORT($object, $json_params, $element)
  {
    $parser = new cmd_import($object, $element, $json_params);
    $this->iba_parser_list["{$parser->operation}"][] = &$parser;
  }

  /**
   * IMPORT configuration from router
   * @param object $json_params			JSON parameters of the command
   * @param domElement $element			XML DOM element of the definition of the command
   */
  function eval_IBA_IMPORT()
  {
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    $ret = addon_connect('IBA', true);
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    if (!empty($this->iba_parser_list))
    {
      $objects = array();
      // One operation groups several parsers
      foreach ($this->iba_parser_list as $operation => $parsers)
      {
        $sub_list = array();
        foreach ($parsers as $parser)
        {
          $op_eval = $parser->eval_operation();
          // Group parsers into evaluated operations
          $sub_list["$op_eval"][] = $parser;
        }

        foreach ($sub_list as $op_eval => $sub_parsers)
        {
          // Run evaluated operation
          $running_conf = '';
          $op_list = explode("\n", $op_eval);
          foreach ($op_list as $op)
          {
            $running_conf .= addon_execute_command('IBA', $op, 'lire dans sdctx');
          }
          // Apply concerned parsers
          foreach ($sub_parsers as $parser)
          {
            $parser->parse($running_conf, $objects);
          }
        }
      }

      $this->iba_parsed_objects = $objects;

      debug_object_conf($objects);
      $SMS_RETURN_BUF .= object_to_json($objects);
    }

    unset($on_error_fct);
    addon_disconnect();

    return SMS_OK;
  }

  /**
   * save parsed objects to database
   */
  function apply_base_IBA_IMPORT($params)
  {
    return set_conf_object_to_db($this->iba_parsed_objects);
  }

  /*
   * #####################################################################################
   * IBA_CREATE
   * #####################################################################################
   */

  /**
   * Decode XML definition of IBA_CREATE command
   * @param string $object object_id
   * @param string $json_params JSON formatted parameters for this object
   * @param DomElement $element command defintion
   */
  function decode_IBA_CREATE($object, $json_params, $element)
  {
    $this->iba_create_list[] = new cmd_create($object, $element, $json_params);
  }
  function eval_IBA_CREATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->iba_create_list as $create)
    {
      $conf = $create->evaluate();
      $this->iba_configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    $this->iba_configuration .= "\n";
    $SMS_RETURN_BUF .= "\n";
    return SMS_OK;
  }

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_device_IBA_CREATE($params)
  {
    debug_dump($this->iba_configuration, "CONFIGURATION TO SEND TO THE IBA");

    $ret = addon_apply_conf('IBA', $this->iba_configuration);

    return $ret;
  }

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_base_IBA_CREATE($params)
  {
    return set_conf_object_to_db($params);
  }

  /*
   * #####################################################################################
   * IBA_DELETE
   * #####################################################################################
   */

  /**
   * Decode XML definition of DELETE command
   * @param string $object object_id
   * @param string $json_params JSON formatted parameters for this object
   * @param DomElement $element command defintion
   */
  function decode_IBA_DELETE($object, $json_params, $element)
  {
    $this->iba_delete_list[] = new cmd_delete($element, $json_params);
  }
  function eval_IBA_DELETE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->iba_delete_list as $delete)
    {
      $conf = $delete->evaluate();
      $this->iba_configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    return SMS_OK;
  }

  /**
   * Apply deleted object to device and if OK add object to the database.
   */
  function apply_device_IBA_DELETE($params)
  {
    debug_dump($this->iba_configuration, "CONFIGURATION TO SEND TO THE IBA");

    $ret = addon_apply_conf('IBA', $this->iba_configuration);

    return $ret;
  }

  /**
   * Apply deleted object to device and if OK add object to the database.
   */
  function apply_base_IBA_DELETE($params)
  {
    return delete_conf_object_in_db($params);
  }

  /*
   * #####################################################################################
   * IBA_UPDATE
   * #####################################################################################
   */

  /**
   * Decode XML definition of UPDATE command
   * @param string $object object_id
   * @param string $json_params JSON formatted parameters for this object
   * @param DomElement $element command defintion
   */
  function decode_IBA_UPDATE($object, $json_params, $element)
  {
    $this->iba_update_list[] = new cmd_update($element, $json_params);
  }
  function eval_IBA_UPDATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->iba_update_list as $update)
    {
      $conf = $update->evaluate();
      $this->iba_configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    return SMS_OK;
  }

  /**
   * Apply updated object to device and if OK add object to the database.
   */
  function apply_device_IBA_UPDATE($params)
  {
    debug_dump($this->iba_configuration, "CONFIGURATION TO SEND TO THE IBA");

    $ret = addon_apply_conf('IBA', $this->iba_configuration);

    return $ret;
  }

  /**
   * Apply updated object to device and if OK add object to the database.
   */
  function apply_base_IBA_UPDATE($params)
  {
    return set_conf_object_to_db($params);
  }
  function eval_CREATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->create_list as $create)
    {
      $conf = $create->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    $this->configuration .= "\n";
    $SMS_RETURN_BUF .= "\n";
    return SMS_OK;
  }
}

?>
