<?php
/*
 * Date : Mars 14, 2014
 * Available global variables
 */

// Transfer the configuration file and the firmware to the apic


require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('apic', 'common.php');
require_once "$db_objects";



function apic_apply_conf($configuration)
{
  global $sdid;

  $configuration = preg_replace('/\s\s+/', '', $configuration);
  $configuration = str_replace("(", "{", $configuration);
  $configuration = str_replace(")", "}", $configuration);
  $configuration_string =(string) '{"flowConfig":['.$configuration.']}';
  $configuration_json = str_replace('}{', '},{', $configuration_string);
  $configuration_array = json_decode($configuration_json, true);
  $sizof_configuration_array = sizeof($configuration_array["flowConfig"]);

  sms_log_error("========JSON CONFIGURATION ===  $configuration_json ======== \n");
  sms_log_error("======== Size of configuration ===  $sizof_configuration_array ======== \n");

  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');

  // Get the URLs from apicConnection class
  $connect = new ApicConnection();
  $url_policy = $connect->get_url_policy();


  for ($i=0; $i<$sizof_configuration_array; $i++) {

  	// get configuration by configuration
  	$configuration = get_configuration($configuration_json, $i);

  	// Get object_action
  	$object_action = get_object_action($configuration);
  	//sms_log_error("======== OBJECT ACTION === \n" . $object_action . "\n ======== \n");

  	// Delete object_action
  	delete_object_action($configuration);
  	//sms_log_error("======== CONFIGURATION To Push === \n" . $configuration . "\n ======== \n");

  	// Get policyId
  	$policyId = get_policyId($configuration);

  	if ($object_action == "delete") {
  		delete_policy($url_policy, $policyId);

  	} else if ($object_action == "update") {
  		delete_policy($url_policy, $policyId);
  		create_policy($url_policy, $configuration);

  	} else {
  		$ret = create_policy($url_policy, $configuration);
  		//sms_log_error("======== CREATE Result ===  $ret ======== \n");
  	}
  }
  return SMS_OK;
}



?>
