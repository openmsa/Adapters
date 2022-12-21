<?php
/*
 * Version: $Id$
 * Created: May 24, 2022
 * Available global variables
 */

require_once 'smsd/sms_common.php';
require_once 'smsd/generic_command.php';

require_once load_once('arista_eos', 'adaptor.php');

class arista_eos_command extends generic_command
{

  function __construct() {
    parent::__construct ();
    $this->parsed_objects = array ();
  }

  /*
   * #####################################################################################
   * IMPORT
   * #####################################################################################
   * $element := xml node 'command'
   */

  /**
   * IMPORT configuration from router
   */
  function eval_IMPORT()
  {
	global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    if (sd_connect() != SMS_OK)
    {
    	return ERR_SD_NETWORK;
    }

   if (!empty($this->parser_list))
    {
      $objects = array();
      // One operation groups several parsers
      foreach ($this->parser_list as $operation => $parsers)
      {
        $sub_list = array();
        foreach($parsers as $parser)
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
            $running_conf .= sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $op);
          }
          // Apply concerned parsers
          foreach ($sub_parsers as $parser)
          {
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

}

?>
