<?php
/*
 * Version: $Id$
 * Created: Jan 12, 2011
 * Available global variables
 *  $sms_sd_info        sd_info structure
 *  $sdid
 *  $sms_module         module name (for patterns)
 *  $ipaddr             ip address of the device sending the syslog
 */

// Get the serial number of the device and check if it is the good one

require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'netasq_configuration.php');


try
{
  netasq_connect($ipaddr);

  $conf = new netasq_configuration($sdid);

  $conf->get_info();

  $ret = $conf->check_serial_number();

  netasq_disconnect();
}
catch(Exception $e)
{
  netasq_disconnect();
  return $e->getCode();
}

return $ret;
?>