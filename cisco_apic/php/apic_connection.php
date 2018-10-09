<?php
/*
 * Version: $Id: srp520_configuration.php 37371 2010-11-30 17:46:40Z tmt $
 * Created: Feb 12, 2009
 */

require_once 'smsd/sms_common.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class ApicConnection extends GenericConnection
{
	var $ip_controller;
	var $port_controller;

	function __construct() {
		$net = get_network_profile();
		$sd=&$net->SD;
		$this->ip_controller = $sd->SD_IP_CONFIG;
		$this->port_controller = $sd->SD_MANAGEMENT_PORT;
	}

	function access_url_config_file()
	{
		return "http://" . $this->ip_controller.":".$this->port_controller . "/api/v0/configuration-file/" ;
	}

	function access_url_firmware()
	{
		return "http://" . $this->ip_controller.":".$this->port_controller . "/api/v0/image-file/" ;
	}

	function access_url_get_config($apic_ip, $apic_port, $networkDeviceId) {
		return "http://". $this->ip_controller.":".$this->port_controller . "/DataAccessService/network-device/$networkDeviceId/config/";
	}

	function get_url_policy() {
		return "http://". $this->ip_controller.":".$this->port_controller . "/api/v0/policy/";
	}

	function get_url_import_devices() {
		return "http://". $this->ip_controller.":".$this->port_controller . "/api/v0/network-device/";
	}
}

?>
