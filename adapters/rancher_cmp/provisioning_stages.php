<?php

$provisioning_stages = array(
  0 => array('name' => 'Lock Provisioning',     'prog' => 'prov_lock'),
  1 => array('name' => 'Service Connectivity',  'prog' => 'rancher_cmp_checkservicestatus'),
  2 => array('name' => 'Register Management IP','prog' => 'prov_register_ip'),
  3 => array('name' => 'Unlock Provisioning',   'prog' => 'prov_unlock')
);

?>