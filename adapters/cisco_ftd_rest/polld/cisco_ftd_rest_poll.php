<?php

require_once 'smsd/sms_common.php';
require_once load_once('cisco_ftd_rest', 'me_connect.php');

$ret =  me_connect();
if ($ret != SMS_OK)
{
  return $ret;
}

return me_disconnect();

?>
