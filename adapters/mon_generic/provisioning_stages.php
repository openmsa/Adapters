<?php

require_once "$db_objects";

$network = get_network_profile();
$SD = &$network->SD;

if ($SD->SD_LOG)
{
    $provisioning_stages = array(
    0 => array('name' => "Icmp testing"),
    1 => array('name' => "Snmp testing"),
    2 => array('name' => "DNS update"),
    );
}
else
{
    $provisioning_stages = array(
    0 => array('name' => "Icmp testing"),
    1 => array('name' => "DNS update"),
    );
}
?>
