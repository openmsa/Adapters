<?php
$provisioning_stages = array(
	0 => array(
		'name' => 'Lock Provisioning',
		'prog' => 'prov_lock'
	),
	1 => array(
		'name' => 'Service Connectivity',
		'prog' => 'nfvo_generic_checkservicestatus'
	),
	2 => array(
		'name' => 'DNS Update',
		'prog' => 'prov_dns_update'
	),
	3 => array(
		'name' => 'Unlock Provisioning',
		'prog' => 'prov_unlock'
	)
);

