<?php

$provisioning_stages_with_license = array(
    array('name' => 'Lock Provisioning',     'prog' => 'prov_lock'),
    array('name' => 'Initial Connection',    'prog' => 'prov_init_conn'),
    array('name' => 'Initial Configuration', 'prog' => 'prov_init_conf'),
    array('name' => 'Update License',        'prog' => 'prov_update_license'),
    array('name' => 'Disconnect',            'prog' => 'prov_disconnect'),
    array('name' => 'DNS Update',            'prog' => 'prov_dns_update'),
    array('name' => 'Unlock Provisioning',   'prog' => 'prov_unlock'),
    array('name' => 'Save Configuration',    'prog' => 'prov_save_conf'),
);

$provisioning_stages_wo_license = array(
    array('name' => 'Lock Provisioning',     'prog' => 'prov_lock'),
    array('name' => 'Initial Connection',    'prog' => 'prov_init_conn'),
    array('name' => 'Initial Configuration', 'prog' => 'prov_init_conf'),
    array('name' => 'Disconnect',            'prog' => 'prov_disconnect'),
    array('name' => 'DNS Update',            'prog' => 'prov_dns_update'),
    array('name' => 'Unlock Provisioning',   'prog' => 'prov_unlock'),
    array('name' => 'Save Configuration',    'prog' => 'prov_save_conf'),
);

?>