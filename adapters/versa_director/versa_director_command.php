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

require_once load_once('versa_director', 'adaptor.php');

class versa_director_command extends generic_command
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

          // Rechercher tous les objets et les rajouter à la liste des commandes à passer
		  // Cas special pour certains objets nécessitant de faire plusieurs GET pour récupérer tous les objets (dépendances)
		  // Un ! est mis en sépateur de chaque requete dans la REST Command du formulaire
		  $list_objects = explode("!",  $op_eval);

          // trouver tous les UUID pour faire les GET
          foreach ($list_objects as $single_object)
          {
		      if ($single_object != '') {
					$cmd = 'GET#' . trim($single_object);
					$parser_list[$cmd][] = $parser;
					}
          }

  /*        $uuid = $parser->evaluate_internal('IMPORT', 'json_params');
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
            $parser_list[$cmd][] = $parser;
          }*/
          // Group parsers into evaluated operations
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

        $this->parsed_objects = array_replace_recursive($this->parsed_objects, $objects);

        debug_object_conf($this->parsed_objects);
        $SMS_RETURN_BUF = object_to_json($this->parsed_objects);
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
	  // Cas special pour certains objets nécessitant de faire plusieurs CREATE pour créer une sequence
	  // Un ! est mis en sépateur de chaque requete dans la REST Command du formulaire
	  $list_commands_rest = explode("!",  $create->evaluate_operation());
	  $list_parameters_rest = explode("!",  $create->evaluate_xml());

	  debug_dump( $list_commands_rest,"##############COMMAND############");
	  debug_dump( $list_parameters_rest,"##############PARAMETERS############");
	  // Pour toutes les commandes à passer ...

	  for ($i=0; $i < count($list_commands_rest); $i++ )
          {
		  if ($list_commands_rest[$i] != '') { // ignorer les commandes vides au cas où
			  $conf = 'POST#'.trim($list_commands_rest[$i]);
			  if (!empty($conf))
			  {
				//$xpath = trim($create->evaluate_xpath());
				//$conf .= 'POST#';  //. urlencode(trim($xpath));


				//echo ("################## {$create->evaluate_xml()} ##############\n");
				//$xml_conf = trim($create->evaluate_xml());
				$conf_lines = preg_split("/\n/", $list_parameters_rest[$i]);
				// MODIF LO : rajoute le TAG pour la séparation des données de DATA CURL
				$conf .= '#';
				foreach ($conf_lines as $conf_line)
				{
				  $conf .= trim($conf_line);
				}
				$this->configuration .= "{$conf}\n";

				debug_dump($conf,"##################################");
				$SMS_RETURN_BUF = $this->configuration;
			  }
			}
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
      $conf = 'PUT#' . trim($update->evaluate_operation());

      if (!empty($conf))
      {
        $xpath = trim($update->evaluate_xpath());
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
      $ret = sd_apply_command_update($this->configuration, true);
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

	  // Cas special pour certains objets nécessitant de faire plusieurs CREATE pour créer une sequence
	  // Un ! est mis en sépateur de chaque requete dans la REST Command du formulaire
	  $list_commands_rest = explode("!",  $delete->evaluate_operation());
	  debug_dump( $list_commands_rest,"##############COMMAND############");

	  // Pour toutes les commandes à passer ...
	  for ($i=0; $i < count($list_commands_rest); $i++ )
          {
		  if ($list_commands_rest[$i] != '') { // ignorer les commandes vides au cas où
			  $conf = 'DELETE#'.trim($list_commands_rest[$i]);
			  if (!empty($conf))
			  {
				$this->configuration .= "{$conf}\n";
				$SMS_RETURN_BUF .= "{$conf}\n";
			  }
			}
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

}

?>