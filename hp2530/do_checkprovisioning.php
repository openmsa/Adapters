<?php
/*
 * Version: $Id: do_checkprovisioning.php 22221 2009-09-30 12:46:20Z tmt $
 * Created: Nov 02, 2009
 * Available global variables
 *  $sms_sd_ctx    pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sdid
 *  $sms_module    module name (for patterns)
 */

// Verb CHECKPROVISIONING


require_once 'smsd/sms_common.php';
// NSP Bugfix 2017.07.28 MOD START
// Modified Device Adaptor Name
require_once load_once('hp2530', 'provisioning_stages.php');
// NSP Bugfix 2017.07.28 MOD END

return require_once 'smsd/do_checkprovisioning.php';

?>