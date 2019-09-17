<?php

// Verb CHECKPROVISIONING
require_once 'smsd/sms_common.php';
require_once "$db_objects";

require_once load_once ( 'netconf_generic', 'provisioning_stages.php' );

return require_once 'smsd/do_checkprovisioning.php';

?>