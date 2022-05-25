<?php
/*
 * Version: $Id: do_checkprovisioning.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: May 24, 2022
 */

// Verb CHECKPROVISIONING

require_once 'smsd/sms_common.php';

require_once load_once('arista_eos', 'provisioning_stages.php');

return require_once 'smsd/do_checkprovisioning.php';

?>
