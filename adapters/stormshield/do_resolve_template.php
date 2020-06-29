<?php
/*
 * Version: $Id: do_resolve_template.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Jun 22, 2011
 * Available global variables
 *  $sms_module         name of the current module (cisco, juniper, fortinet...)
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid               name of the current SD
 *  $template_path		absolute path of the template
 */

// Verb JSRESOLVETEMPLATE

require_once 'smsd/sms_common.php';
require_once load_once('stormshield', 'netasq_configuration.php');
require_once "$db_objects";

$conf = new netasq_configuration($sdid);

// Aditional variable for data files:
$add_vars['CLI_PREFIX'] = $conf->get_cli_prefix();
$add_vars['ABONNE'] = $conf->get_abo();
$add_vars['DO_RESOLVE_TEMPLATE'] = true;

$resolved_template = resolve_template($sdid, $template_path, $add_vars, $smarty_function);

sms_send_user_ok($sms_csp, $sdid, "\"$resolved_template\"");

return SMS_OK;

?>
