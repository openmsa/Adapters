<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_command.php';

require_once "$db_objects";

class inventory_management_command extends generic_command {

  function __construct() {
    parent::__construct ();
    $this->parsed_objects = array ();
  }

  // return data of the database
  function eval_IMPORT() {
    global $SMS_RETURN_BUF;

    $net = get_network_profile();
    $sd = $net->SD;

    // Filter objects by MS name (object_name)
    $crud_objects = array();
    if (!empty($this->parser_list))
    {
      foreach ($this->parser_list as $parsers)
      {
        foreach($parsers as $parser)
        {
          if (!empty($sd->smarty[$parser->object_name]))
          {
            $crud_objects[$parser->object_name] = $sd->smarty[$parser->object_name];
          }
        }
      }
    }

    // set parsed_objects to store objects in DB
    $this->parsed_objects = array_replace_recursive($this->parsed_objects, $crud_objects);

    debug_object_conf($this->parsed_objects);
    $SMS_RETURN_BUF = object_to_json($this->parsed_objects);

    return SMS_OK;
  }
}

?>
