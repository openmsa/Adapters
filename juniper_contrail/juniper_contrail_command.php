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
require_once load_once('smsd', 'cmd_create_xml.php');
require_once load_once('smsd', 'cmd_update_xml.php');
require_once load_once('smsd', 'cmd_delete_xml.php');
require_once load_once('smsd', 'cmd_import_xml.php');
require_once load_once('smsd', 'cmd_list.php');

require_once load_once('juniper_contrail', 'adaptor.php');
class juniper_contrail_command extends generic_command
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

          //MODIF LO
          //$cmd = trim($op_eval) . '&xpath=' . urlencode(trim($xpath_eval));
          // Rechercher tous les objets et les rajouter à la liste des commandes à passer
          $cmd = 'GET#' . trim($op_eval);

          $uuid = $parser->evaluate_internal('IMPORT', 'json_params');
          if ($uuid != '0')
          { // un seul objet à importer avec l'uuid donné en entrée
            echo ("UN SEUL OBJET\n");
            if ($uuid == '') // Cas avec import sur la base d'un nom humain et pas d'un UUID
              $parser_list[$cmd][] = $parser;
            else
              $parser_list[$cmd . "/" . $uuid][] = $parser;
          }
          else
          { // sinon parser tous les objets
            echo ("LISTE d'OBJET\n");

            $list_objects = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd . "s");
            // trouver tous les UUID pour faire les GET
            foreach ($list_objects->xpath('//uuid') as $uuid_object)
            {
              $parser_list[$cmd . "/" . $uuid_object][] = $parser;
            }
          }
          // Group parsers into evaluated operations
          //$parser_list[$cmd][] = $parser;
          // FIN MODIF
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

        $this->parsed_objects = $objects;
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
  function eval_CREATE()
  {
    global $SMS_RETURN_BUF;

    foreach ($this->create_list as $create)
    {
      $conf = 'POST#' . trim($create->evaluate_operation());
      if (!empty($conf))
      {
        $xpath = trim($create->evaluate_xpath());
        // MODIF LO
        $conf .= urlencode(trim($xpath));
        //$conf .= '&element=';
        echo ("################## {$create->evaluate_xml()} ##############\n");

        // FIN MODIF LO
        $xml_conf = trim($create->evaluate_xml());
        $conf_lines = preg_split("/\n/", $xml_conf);
        // MODIF LO : rajoute le TAG pour la séparation des données de DATA CURL
        $conf .= '#';
        // FIN AJOUT
        foreach ($conf_lines as $conf_line)
        {
          // MODIF LO
          //$conf .= urlencode(trim($conf_line));
          $conf .= trim($conf_line);
          // FIN MODIF
        }
        $this->configuration .= "{$conf}\n";
        $SMS_RETURN_BUF = "";
      }
    }
    return SMS_OK;
  }

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_device_CREATE(&$params)
  {
    $ret = SMS_OK;
    if (!empty($this->configuration))
    {
      debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
      $ret = sd_apply_conf($this->configuration, true, $params);
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
      $conf = 'PUT#' . trim($update->evaluate_operation());

      if (!empty($conf))
      {
        $xpath = trim($update->evaluate_xpath());
        $conf .= "/" . urlencode(trim($xpath));
        $xml_conf = trim($update->evaluate_xml());
        $conf_lines = preg_split("/\n/", $xml_conf);
        // MODIF LO : rajoute le TAG pour la séparation des données de DATA CURL
        $conf .= '#';
        // FIN AJOUT
        foreach ($conf_lines as $conf_line)
        {
          $conf .= trim($conf_line);
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
  function apply_device_UPDATE(&$params)
  {
    $ret = SMS_OK;
    if (!empty($this->configuration))
    {
      debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");
      $ret = sd_apply_command_update($this->configuration, true, $params);
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
      $conf = 'DELETE#' . trim($delete->evaluate_operation());

      if (!empty($conf))
      {
        $xpath = trim($delete->evaluate_xpath());
        $conf .= "/" . urlencode(trim($xpath));
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

    $ret = sd_apply_command_delete($this->configuration, true);
    // $ret = sd_apply_conf($this->configuration, true);
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
}

?>