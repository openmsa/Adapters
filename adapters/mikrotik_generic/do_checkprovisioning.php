<?php 
require_once 'smsd/sms_common.php';
require_once "$db_objects";

require_once load_once('mikrotik_generic', 'provisioning_stages.php');

$conf_pflid = 0;

unset($configuration);
get_conf_from_config_file($sdid, $conf_pflid, $configuration, 'FORTI_VM_CONFIG', 'Configuration');

return require_once 'smsd/do_checkprovisioning.php';

?>
