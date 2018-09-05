<?php
/*
 * Version: $Id$
* Created: Jul 03, 2015
* Available global variables
*  $sms_csp            pointer to csp context to send response to user
* 	$sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
* 	$SMS_RETURN_BUF     string buffer containing the result
*/

require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'cmd_create.php');
require_once load_once('smsd', 'cmd_read.php');
require_once load_once('smsd', 'cmd_update.php');
require_once load_once('smsd', 'cmd_delete.php');
require_once load_once('smsd', 'cmd_import.php');
require_once load_once('smsd', 'cmd_list.php');

require_once load_once('smsd', 'generic_command.php');

class mon_generic_command extends generic_command
{

  function __construct()
  {
    parent::__construct();
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
    $SMS_RETURN_BUF ='';

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
    return SMS_OK;
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
    return SMS_OK;
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
    return SMS_OK;
  }
}

?>
