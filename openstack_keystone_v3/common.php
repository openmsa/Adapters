<?php
/*
 * Date : Oct 19, 2007
 */

// Script description
require_once 'smsd/sms_common.php';


/**
 * Generate flowID for each flows from flowConfig
 * @param unknown $configuration
 * @return $configWithFlowID
 */
function get_flowConfigWithFlowID(&$configuration)
{
  $configWithFlowID = array(
      'flowConfig' => array()
  );

  $flowConfig = "";

  $flow_config = json_decode($configuration, true);

  for ($i = 0; $i < sizeof($flow_config["flowConfig"]); $i++)
  {

    $array = array();

    $action = $flow_config["flowConfig"][$i]["actions"];

    for ($j = 0; $j < sizeof($action); $j++)
    {

      $action[$j] = "--$action[$j]--";
      var_dump($action);
    }

    $flow_config["flowConfig"][$i]["actions"] = $action;
    $flow_array = $flow_config["flowConfig"][$i];
    $flow_json = json_encode($flow_array);

    // Generate flow ID
    $flow_id = generateFlowId($flow_json);

    // Attache ID on the flow
    $array[$flow_id] = $flow_array;

    // Add flow with ID on the new flowConcifg File
    $configWithFlowID["flowConfig"][$i] = $array;

    $flowWithID = (string) json_encode($configWithFlowID["flowConfig"][$i]);
    $flowConfig .= $flowWithID . "\n";
  }

  return $flowConfig;
}

/**
 * To structure flows line by line
 * @param json $configuration
 * @return string
 */
function do_structure_flowConfig(&$configuration)
{
  $running_conf = array(
      'flowConfig' => array()
  );

  $flow_config = json_decode($configuration, true);
  $sizeOfFlow_config = sizeof($flow_config["flowConfig"]);

  for ($i = 0; $i < $sizeOfFlow_config; $i++)
  {

    $array = array();

    $flow = $flow_config["flowConfig"][$i];

    if ($i != $sizeOfFlow_config - 1)
    {
      $flow_line = json_encode($flow) . ",";
    }
    else
    {
      $flow_line = json_encode($flow);
    }
    $flowConfig_lines .= $flow_line . "\n";
  }

  $running_conf = "{$flowConfig_lines}";

  return $running_conf;
}

/**
 * Get nodes configuration from flowConfig
 * @param unknown $configuration
 */
function get_nodeConfigWithID(&$configuration)
{
  $flow_config = json_decode($configuration, true);

  $nodeConfigWithID = array(
      'nodesConfig' => array()
  );

  $nodeConfig = "";

  for ($i = 0; $i < sizeof($flow_config["flowConfig"]); $i++)
  {

    $array = array();

    $flow_array = $flow_config["flowConfig"][$i];
    $node = $flow_array["node"];
    $node_json = json_encode($node);

    // Generate flow ID
    $nodeID = $node["id"];

    // Attache ID on the flow
    $array[$nodeID] = $node;

    // Add flow with ID on the new flowConcifg File
    $nodeConfigWithID["nodesConfig"][$i] = $array;

    $nodeWithID = (string) json_encode($nodeConfigWithID["nodesConfig"][$i]);
    $nodeConfig .= $nodeWithID . "\n";
  }

  return $nodeConfig;
}

/**
 * Get nodes configuration from nodeLearnt API
 * @param unknown $configuration
 */
function get_nodesListWithID(&$configuration)
{
  $configuration = json_decode($configuration, true);
  $nodeData = $configuration["nodeData"];

  $nodeConfigWithID = array(
      'nodesConfig' => array()
  );

  $nodeConfig = "";

  for ($i = 0; $i < sizeof($nodeData); $i++)
  {

    $array = array();

    $node = $nodeData[$i];

    // Generate flow ID
    $nodeID = $node["nodeId"];

    // Attache ID on the flow
    $array[$nodeID] = $node;

    // Add flow with ID on the new flowConcifg File
    $nodeConfigWithID["nodesConfig"][$i] = $array;

    $nodeWithID = (string) json_encode($nodeConfigWithID["nodesConfig"][$i]);
    $nodeConfig .= $nodeWithID . "\n";
  }

  return $nodeConfig;
}

/**
 * Generate FlowID
 * @param Array $flow
 * @return string flowID
 */
function generateFlowId(&$flow)
{
  $flow_name = get_flow_name($flow);
  $nodID = get_flow_nodeID($flow);
  $flow_id = $flow_name . $nodID;

  return $flow_id;
}

/**
 * GET FLOW NOM (OpenDaylight)
 * @param json $configuration
 * @return String
 */
function get_flow_name(&$configuration)
{
  $conf_tab = json_decode($configuration, true);
  $flow_name = $conf_tab['name'];

  return $flow_name;
}

/**
 * GET NODEID from flow configuration
 * @param json $configuration
 * @return String
 */
function get_flow_nodeID(&$configuration)
{
  $conf_tab = json_decode($configuration, true);
  $flow_nodes = $conf_tab['node'];
  $flow_nodes_id = $flow_nodes["id"];

  return $flow_nodes_id;
}

/**
 * Get NODE TYPE from flow configuration
 * @param json $configuration
 * @return String
 */
function get_flow_node_type($configuration)
{
  $conf_tab = json_decode($configuration, true);
  $flow_nodes = $conf_tab['node'];
  $flow_nodes_type = $flow_nodes["type"];
  if (empty($flow_nodes_type))
  {
    $flow_nodes_type = "OF"; //set default value
  }
  return $flow_nodes_type;
}

/**
 * Get Flow from flowConfig
 * @param json $configuration_json
 * @param integer $i
 * @return string
 */
function get_configuration(&$configuration_json, $i)
{
  $flow_config = json_decode($configuration_json, true);

  $flow = $flow_config["flowConfig"][$i];
  $configuration = json_encode($flow);

  return $configuration;
}

/**
 * Get object action : delete, update, create
 * @param json $configuration
 */
function get_object_action(&$configuration)
{
  $flow = json_decode($configuration, true);
  $action = $flow['object_action'];

  return $action;
}

/**
 * Delete objet_action variable on flow
 * @param unknown $configuration
 * @return string
 */
function delete_object_action(&$configuration)
{
  $flow = json_decode($configuration, true);
  Unset($flow['object_action']);

  $configuration = json_encode($flow);
  return $configuration;
}

?>