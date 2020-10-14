<?php
/*
 * sle
 * Date : Jun 06, 2014
 * Available global variables
 */

// Add or modified the flows to openstack controller
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('opendaylight', 'common.php');
require_once "$db_objects";

/**
 * <p><h1>openstack_apply_flow_conf($configuration)</h1></p>
 * Function to add or install flow to the openstack Controller
 * @param unknown $configuration
 * @return unknown|string
 */
function opendaylight_apply_flow_conf($configuration)
{
  global $sdid;
  global $apply_errors;
  $net = get_network_profile();
  $sd = &$net->SD;

  // Get controller user Login / password
  $login = $sd->SD_LOGIN_ENTRY;
  $password = $sd->SD_PASSWD_ENTRY;
  $connect = new opendaylightConnection();

  // Rebuild a flowConfig from OBMF config flows
  $config = str_replace('}{', '},{', $configuration);
  $configuration_json = '{"flowConfig": [' . $config . ']}';

  $configuration_array = json_decode($configuration_json, true);
  $sizof_configuration_array = sizeof($configuration_array["flowConfig"]);

  for ($i = 0; $i < $sizof_configuration_array; $i++)
  {

    // get configuration by configuration
    $configuration = get_configuration($configuration_json, $i);

    // Get Flow name, Node ID, Node Type and  Action
    $action = get_object_action($configuration);
    $configuration = delete_object_action($configuration);

    $nodeID = get_flow_nodeID($configuration);
    $nodeType = get_flow_node_type($configuration);
    $flow_name = get_flow_name($configuration);

    // Get Controller API REST url
    $url = $connect->get_url_put_flow($nodeID, $nodeType) . $flow_name;

    // Curl CMD
    $delay = 50;
    $cmd_delete_flow = "curl -u " . $login . ":" . $password . " --connect-timeout {$delay} --max-time {$delay} -X DELETE " . "'" . $url . "'";
    $cmd_put_flow = "curl -u " . $login . ":" . $password . " --connect-timeout {$delay} --max-time {$delay} -H 'Content-type: application/json' -X PUT -d " . "'" . $configuration . "' " . "'" . $url . "'";

    // Start shell execution cmd
    if ($action == "delete")
    {
      sms_log_debug(15, " =============================== DELETE FLOW ========================================\n");
      sms_log_debug(15, " Delete the existing flow : " . $cmd_delete_flow . "\n");
      $ret = shell_exec($cmd_delete_flow);
      sms_log_debug(15, " result:" . $ret . " \n");
      if (strpos($ret, 'exist') == true)
      {
        sms_log_error("Error while deleting: " . $ret . " --> command: " . $cmd_delete_flow . "\n");
        return $ret;
      }
      else
      {
        sms_log_debug(15, " Success ! \n");
      }
      sms_log_debug(15, " ============================= END DELETE FLOW ======================================\n");
      return SMS_OK;
    }
    else if ($action == "update")
    {
      sms_log_debug(15, " =============================== UPDATE FLOW ========================================\n");
      sms_log_debug(15, " Delete the existing flow : " . $cmd_delete_flow . "\n");
      $ret = shell_exec($cmd_delete_flow);
      sms_log_debug(15, " Add new flow : " . $cmd_put_flow . "\n");
      $ret = shell_exec($cmd_put_flow);
      if (strpos($ret, 'ucce') == false)
      {
        sms_log_error("Error while adding: " . $ret . " --> command: " . $cmd_put_flow . "\n");
        return $ret;
      }
      else
      {
        sms_log_debug(15, " Success ! \n");
      }
      sms_log_debug(15, " ============================= END UPDATE FLOW ======================================\n");
    }
    else
    {
      sms_log_debug(15, " =============================== ADD FLOW ========================================\n");
      sms_log_debug(15, " Add new flow : " . $cmd_put_flow . "\n");
      $ret = shell_exec($cmd_put_flow);
      if (strpos($ret, 'ucce') == false)
      {
        sms_log_error("Error while adding: " . $ret . " --> command: " . $cmd_put_flow . "\n");
        return $ret;
      }
      else
      {
        sms_log_debug(15, " Success ! \n");
      }
      sms_log_debug(15, " =============================== END ADD FLOW ========================================\n");
    }

    // Verification of the shell exec status
    if (!$ret)
    {
      sms_log_debug(15, " =============================== ERROR CMD FLOW ========================================\n");
      sms_log_error("Apply flow error for: " . $flow_name . " -> " . $ret . "\n");
      sms_log_debug(15, " ============================= END ERROR CMD FLOW ======================================\n");

      return $ret;
    }
  }

  return SMS_OK;
}

?>
