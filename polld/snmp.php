<?php
/*
 * Version: $Id: snmp.php 88668 2014-10-22 09:05:58Z ydu $
 * Created: Aug 12, 2008
 */

// SNMP access functions

require_once 'smsd/sms_common.php';

function getsnmp($oid, $sd_ip_addr, $community, $version = '2c')
{

  $ret = exec_local(__FILE__ . ':' . __LINE__, "snmpget -Ov -Oq -Oa -v $version -c $community $sd_ip_addr $oid", $output);
  if ($ret !== SMS_OK)
  {
    return '';
  }

  array_pop($output); // remove SMS_OK
  if (empty($output))
  {
    return '';
  }

  $result = '';
  foreach($output as $line)
  {
    if ((strpos($line, "No Such Instance currently exists") !== false) || (strpos($line, "Missing object name") !== false))
    {
      return '';
    }
    $result .= trim($line) . "\n";
  }

  return $result;
}


?>