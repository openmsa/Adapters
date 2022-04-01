<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once ('smsd', 'generic_command.php');

require_once "$db_objects";

class inventory_management_command extends generic_command {

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
    // parsed_objects is empty for the first MS
    if (empty($this->parsed_objects))
    {
      $this->parsed_objects = $crud_objects;
    }
    else
    {
      $this->parsed_objects = array_merge_recursive($this->parsed_objects, $crud_objects);
    }

    $SMS_RETURN_BUF = json_encode($this->parsed_objects, JSON_FORCE_OBJECT);

    return SMS_OK;
  }
}

?>
