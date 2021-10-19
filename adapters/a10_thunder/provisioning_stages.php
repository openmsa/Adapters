<?php
$provisioning_stages = array(
    0 => array(
        'name' => 'Lock Provisioning',
        'prog' => 'prov_lock'
    ),
    1 => array(
        'name' => 'Initial Connection',
        'prog' => 'prov_init_conn'
    ),
    2 => array(
        'name' => 'Initial Configuration',
        'prog' => 'prov_init_conf'
    ),
    3 => array(
        'name' => 'Register Management IP',
        'prog' => 'prov_register_ip'
    ),
    4 => array(
        'name' => 'Unlock Provisioning',
        'prog' => 'prov_unlock'
    ),
    5 => array(
        'name' => 'Save Configuration',
        'prog' => 'prov_save_conf'
    )
);

?>