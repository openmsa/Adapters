<?php

// Device adaptor
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('juniper_contrail', 'me_connect.php');
require_once load_once('juniper_contrail', 'me_apply_conf.php');

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = me_connect($login, $passwd);

  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = me_disconnect();

  return $ret;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false)
{
  if ($need_sd_connection)
  {
    $ret = sd_connect();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
  }

  $ret = me_apply_conf($configuration);

  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  return $ret;
}

?>