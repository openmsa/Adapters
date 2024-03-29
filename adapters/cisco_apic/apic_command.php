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
require_once 'smsd/generic_command.php';

require_once load_once('cisco_apic', 'adaptor.php');
require_once load_once('cisco_apic', 'common.php');

class apic_command extends generic_command
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

/**
   * IMPORT configuration from router
   * @param object $json_params			JSON parameters of the command
   * @param domElement $element			XML DOM element of the definition of the command
   */
  function eval_IMPORT()
  {
    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    $connect = new ApicConnection();
    $url_devices = $connect->get_url_import_devices();
    $url_policy =  $connect->get_url_policy();

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
            if ($op == "get devices") {

            	$ch = curl_init();
            	curl_setopt($ch, CURLOPT_URL, $url_devices);
            	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            	curl_setopt($ch, CURLOPT_VERBOSE, 1);
            	$devices = curl_exec ($ch);

            	curl_close($ch);

            	sms_log_error(" =============================== DEVICES Config : $devices ========================================\n");

            	$running_conf = get_config_line($devices);
            	sms_log_error("=================================== \n $running_conf \n =================================\n");

            } else if ($op == "get policy") {
              sms_log_error(" Import POLICY LIST \n");

              sms_log_error(" =============================== POLICY LIST : $url_policy ========================================\n");

              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL, $url_policy);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
              curl_setopt($ch, CURLOPT_VERBOSE, 1);
              $policy = curl_exec ($ch);

              curl_close($ch);

              $running_conf = get_config_line($policy);

              sms_log_error("=================================== \n $running_conf \n =================================\n");

            } else {
              sms_log_error(" Missing commande to IMPORT Flow/Node \n");

            }
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
