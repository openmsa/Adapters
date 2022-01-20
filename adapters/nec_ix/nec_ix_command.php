<?php
/*
 * Version: $Id$
 * Created: Dec 12, 2018
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 * 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 * 	$SMS_RETURN_BUF     string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'generic_command.php');

require_once load_once('nec_ix', 'adaptor.php');

class nec_ix_command extends generic_command
{
  var $parser_list;
  var $parsed_objects;
  var $create_list;
  var $delete_list;
  var $list_list;
  var $read_list;
  var $update_list;
  var $configuration;
  function __construct()
  {
    $this->parser_list = array();
    $this->create_list = array();
    $this->delete_list = array();
    $this->list_list = array();
    $this->read_list = array();
    $this->update_list = array();
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

    if (sd_connect() != SMS_OK)
    {
      return ERR_SD_NETWORK;
    }

    $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "conf", '(config)#');

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
            //$running_conf .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op,$sms_sd_ctx->getPrompt());
            $running_conf .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op, '(config)#');
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
      $SMS_RETURN_BUF .= json_encode($objects);
    }

    $expect_response = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "exit", $sms_sd_ctx->getPrompt());

    unset($on_error_fct);
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
      $conf = $create->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
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
      $conf = $update->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
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
      $conf = $delete->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
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

  /*
   * #####################################################################################
   * LIST
   * #####################################################################################
   */
  function eval_LIST()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->list_list as $list)
    {
      $conf = $list->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    return SMS_OK;
  }

  /*
   * #####################################################################################
   * READ
   * #####################################################################################
   */
  function eval_READ()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->read_list as $read)
    {
      $conf = $read->evaluate();
      $this->configuration .= $conf;
      $SMS_RETURN_BUF .= $conf;
    }
    return SMS_OK;
  }
}

?>
