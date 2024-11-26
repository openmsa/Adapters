<?php
/*
 * Version: $Id: openstack
 * Created: Jun 06, 2014
 *
 * URLs to used to access the openstack Controller API
 */
require_once 'smsd/sms_common.php';
require_once "$db_objects";
class openstackConnection
{
  var $ip_controller;
  var $port_controller;

  function __construct()
  {
    $net = get_network_profile();
    $sd = &$net->SD;
    $this->ip_controller = $sd->SD_IP_CONFIG;
    $this->port_controller = $sd->SD_MANAGEMENT_PORT;
  }
  function get_url_put_flow($nodeID, $nodeType)
  {
    return "http://" . $this->ip_controller . ":" . $this->port_controller . "/controller/nb/v2/flowprogrammer/default/node/" . $nodeType . "/" . $nodeID . "/staticFlow/";
  }
  function get_url_import_flow()
  {
    return "http://" . $this->ip_controller . ":" . $this->port_controller . "/controller/nb/v2/flowprogrammer/default/";
  }
  function get_url_read_flow($nodeID, $flow_name)
  {
    return "http://" . $this->ip_controller . ":" . $this->port_controller . "/controller/nb/v2/flowprogrammer/default/OF/" . $nodeID . "/staticFlow/" . $flow_name;
  }
  function get_availibility()
  {
    return "http://" . $this->ip_controller . ":" . $this->port_controller;
  }
}

?>
