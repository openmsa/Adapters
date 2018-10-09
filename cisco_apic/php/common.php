<?php
/*
 * Date : Oct 19, 2007
*/

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';

//require_once load_once('apic', 'apic_connection.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported"
);

//######## SDN ############
/**
 * Get policy, device discovery list line by line
 * @param json $configuration
 * @return string
 */
function get_config_line($configuration) {
	$configuration = json_decode($configuration, true);
	$configuration = $configuration["response"];
	$conf_size = sizeOf($configuration);
	$devices = "";

	for ($i=0; $i<$conf_size; $i++) {

		if ($i<$conf_size -2) {
			$devices .= json_encode($configuration[$i]) . ",\n";
		} else {
			$devices .= json_encode($configuration[$i]) . "\n";
		}
	}
	return $devices;
}


/**
 * Get object action : delete, update, create
 * @param json $configuration
 */
function get_object_action(&$configuration) {

	$conf = json_decode($configuration, true);
	$action = $conf['object_action'];

	return $action;
}

/**
 * Delete objet_action variable on flow
 * @param unknown $configuration
 * @return string
 */
function delete_object_action(&$configuration) {
	$conf = json_decode($configuration, true);
	Unset($conf['object_action']);

	$configuration = json_encode($conf);
	return $configuration;
}

function get_policyId(&$configuration) {

	$conf = json_decode($configuration, true);
	if (!empty($conf['policyId'])) {
		$policyId = $conf['policyId'];
	}

	return $policyId;
}

/**
 * Get Flow from flowConfig
 * @param json $configuration_json
 * @param integer $i
 * @return string
 */
function get_configuration(&$configuration_json, $i) {

	$flow_config = json_decode($configuration_json, true);

	$flow = $flow_config["flowConfig"][$i];
	$configuration = json_encode($flow);

	return $configuration;
}
/**
 * Apply policy to the controller APIC
 * @param string $url
 * @param json $configuration
 */
function create_policy($url, &$configuration) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch,CURLOPT_HTTPHEADER,array (
	"Content-Type: application/json; charset=UTF-8",
	"Expect: 100-continue"
			));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $configuration);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	$response = curl_exec ($ch);

	return $response ;
}

/**
 * Delete policy on the controller from policyId
 * @param string $url
 * @param string $policyId
 */
function delete_policy($url, $policyId) {
	$ch = curl_init($url . $policyId);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch,CURLOPT_HTTPHEADER,array (
	"Content-Type: application/json; charset=UTF-8",
	"Expect: 100-continue"
			));
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	$response = curl_exec($ch);

	return $response ;
}

//####### end SDN ########

/*** $SDID connection to $networkDeviceId ****/
/**
 * <p><h1><em> get_networkDeviceId_from_apic </em></h1></p>
 * <p> function get_networkDeviceId_from_apic($sdid, $apic_ip, $apic_port) </p>
 * Do the correspondapice between the MSA device-id and the apic device-id
 * @param $sdid_ip - device-id on SOC
 * @return $networkDeviceId - device-id from apic corresponding the $sdid
 */
function get_networkDeviceId_from_apic($sdid_ip, $apic_ip, $apic_port) {

  $apic_network_device_url = "http://".$apic_ip . ":" . $apic_port . "/DataAccessService/network-device/";
  $network_device_json = file_get_contents($apic_network_device_url);
  $list_apic_devices_json = json_decode($network_device_json);

  foreach($list_apic_devices_json->response as $row)
  {
    foreach($row as $key => $val)
    {
      if ($key == "networkDeviceId") {
        $ndid = $val;
      }
      if ($key == "managementIpAddress") {
        $ipAdd_ndid[$val] = $ndid;
      }
    }
  }

  $networkDeviceId = $ipAdd_ndid[$sdid_ip];

  return $networkDeviceId;
}

?>