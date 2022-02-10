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

require_once load_once('smsd', 'generic_command.php');

class mon_generic_command extends generic_command
{
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
    global $SMS_RETURN_BUF;
    $SMS_RETURN_BUF = '{}'; // empty json
    return SMS_OK;
  }
}

?>
