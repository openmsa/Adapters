<?php
/*
 * Version: $Id: do_get_archive_conf.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Jun 18, 2010
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 *  $folder        the folder where to decompress files
 */

// Verb JSGETARCHIVECONF

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'netasq_configuration.php');

echo "Retrieving backup archive\n";

$conf = new netasq_configuration($sdid);

$thread_id = $conf->thread_id;

$ret = $conf->get_running_conf($folder);

return $ret;

?>