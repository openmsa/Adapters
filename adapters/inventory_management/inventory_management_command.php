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

    $crud_object = array();
    if (!empty($sd->SD_CRUD_OBJECT_list))
    {
      foreach ($sd->SD_CRUD_OBJECT_list as $key => $value)
      {
        $key_array = explode('.', $key);
        if (empty($key_array))
        {
          continue;
        }

        $ms_name = $key_array[0];
        $object_id = $key_array[1];
        $var = $key_array[2];

        if (!isset($crud_object[$ms_name]))
        {
          $crud_object[$ms_name] = array();
        }
        if (!isset($crud_object[$ms_name][$object_id]))
        {
          $crud_object[$ms_name][$object_id] = array();
        }
        if (count($key_array) == 3 ) {
          $crud_object[$ms_name][$object_id][$var] = $value;
        }
        else {
          if (!isset($crud_object[$ms_name][$object_id][$var]))
          {
            $crud_object[$ms_name][$object_id][$var]= array();
          }
          else
          {
            if (!isset($crud_object[$ms_name][$object_id][$var][$key_array[3]]))
            {
              $crud_object[$ms_name][$object_id][$var][$key_array[3]]=array();
            }
          }
          $crud_object[$ms_name][$object_id][$var][$key_array[3]][$key_array[4]]=$value;
         }
      }
    }

    $SMS_RETURN_BUF = json_encode($crud_object, JSON_FORCE_OBJECT);

    return SMS_OK;
  }
}

?>
