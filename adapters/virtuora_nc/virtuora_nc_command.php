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

require_once load_once('virtuora_nc', 'adaptor.php');

class virtuora_nc_command extends generic_command
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
        $parser_list = array();

        foreach ($this->parser_list as $parser)
        {
          $op_eval = $parser->evaluate_internal('IMPORT', 'operation');
          $xpath_eval = $parser->evaluate_internal('IMPORT', 'xpath');
			  //echo "&&&&&&&&&&&&&&&&&&&&&&&&&&&& ".$xpath_eval." &&&&&&&&&&&&&&&&&&&&&&&&&&&&&";


          if(strlen($xpath_eval)> 0)
          {
              $path_list = preg_split('@##@', $xpath_eval, 0, PREG_SPLIT_NO_EMPTY);

              foreach ($path_list as $xpth) {
		  //echo "&&&&&&&&&&&&&&&&&&&&&&&&&&&& ".$xpth." &&&&&&&&&&&&&&&&&&&&&&&&&&&&&";
                  $cmd = trim($op_eval). "' -d'{".$xpth."}#POST";
                  $parser_list[$cmd][] = $parser;
              }
          }
          else
          {
              $cmd = trim($op_eval) . "' -d'{}#GET";
              // Group parsers into evaluated operations
              $parser_list[$cmd][] = $parser;
          }

        }

        foreach ($parser_list as $op_eval => $sub_parsers)
        {
          // Run evaluated operation
          $op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);
          foreach ($op_list as $op)
          {
            $running_conf = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
            //debug_dump($sms_sd_ctx->get_raw_xml());
            // Apply concerned parsers
            foreach ($sub_parsers as $parser)
            {
              $parser->parse($running_conf, $objects);
            }
          }
        }

        $this->parsed_objects = array_merge_recursive($this->parsed_objects, $objects);

        debug_object_conf($this->parsed_objects);
        $SMS_RETURN_BUF = object_to_json($this->parsed_objects);
      }

      sd_disconnect();
    }
    catch (Exception $e)
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
  function eval_CREATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->create_list as $create)
    {
      $conf = trim($create->evaluate_operation());

      if (!empty($conf))
      {
          $xml_conf = trim($create->evaluate_xml());
          $xml_conf_str = str_replace("\n", '', $xml_conf);
          $conf.="' -d '".$xml_conf_str."#POST";

          $this->configuration .= "{$conf}\n";
          $SMS_RETURN_BUF .= "{$conf}\n";
      }

    }
echo "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n";
echo $SMS_RETURN_BUF;

    return SMS_OK;
  }

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_device_CREATE($params)
  {
    $ret = SMS_OK;
    if (!empty($this->configuration))
    {
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
  function eval_UPDATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->update_list as $update)
    {
      $conf = trim($update->evaluate_operation());

      if (!empty($conf))
      {
          $xml_conf = trim($update->evaluate_xml());
          $xml_conf_str = str_replace("\n", '', $xml_conf);
          $conf.="' -d '".$xml_conf_str;

          $this->configuration .= "{$conf}\n";
          $SMS_RETURN_BUF .= "{$conf}\n";
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
    if (!empty($this->configuration))
    {
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

    foreach ($this->delete_list as $delete)
    {
        $conf = trim($delete->evaluate_operation());

        if (!empty($conf))
        {
            $xml_conf = trim($delete->evaluate_xml());
            $xml_conf_str = str_replace("\n", '', $xml_conf);
            $conf.="' -d '".$xml_conf_str."#DELETE";
	    //echo "\n*************".$conf."***************************\n";
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

    $ret = sd_apply_conf($this->configuration, true);

    return $ret;
  }
}

?>
