<?php
/*
 * Version: $Id: do_checkprovisioning.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Jun 27, 2008
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sms_module    module name (for patterns)
 *  $archive       archive to uncompress
 *  $folder        folder where drop files
 */

// Verb JSUNARCHIVECONF

require_once 'smsd/sms_common.php';
require_once load_once('netasq', 'netasq_unarchive.php');

$thread_id = $_SERVER['THREAD_ID'];

return netasq_unarchive_conf($thread_id, $archive, $folder);

?>
