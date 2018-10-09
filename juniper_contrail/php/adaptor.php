<?php

// Device adaptor
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('juniper_contrail', 'juniper_contrail_connect.php');
require_once load_once('juniper_contrail', 'juniper_contrail_apply_conf.php');
//require_once load_once('juniper_contrail', 'juniper_contrail_apply_command_delete.php');


require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = juniper_contrail_connect($login, $passwd);

  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = juniper_contrail_disconnect();

  return $ret;
}

/***
 Rajoute la clé UUID à l'objet
 */
function addUUIDKey($haystack, $uuid)
{
  foreach ($haystack as $key => $value)
  {
    if (is_array($value))
    {
      $output[$key] = addUUIDKey($value, $uuid);
    }
    else
    {
      $output[$key] = $value;
      $output['uuid'] = $uuid;
    }
  }
  return $output;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false, &$params)
{
  if ($need_sd_connection)
  {
    sd_connect();
  }

  $created_uuid = "";
  $ret = juniper_contrail_apply_conf($configuration, $created_uuid);
  $params = addUUIDKey($params, $created_uuid);

  debug_dump($params, "$$$$$$$$$$$$$$$$  CREATE  $$$$$$$$$$$$$$$$$$$$$$$$\n");

  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  return $ret;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_command_update($configuration, $need_sd_connection = false, &$params)
{
  if ($need_sd_connection)
  {
    sd_connect();
  }
  debug_dump($configuration, "$$$$$$$$$$$$$$  CONFIGURATION $$$$$$$$$$$$$$$$$$\n");

  $created_uuid = "";
  $ret = juniper_contrail_apply_conf($configuration, $created_uuid);
  // AJOUT 28/1/2015
  echo ("-----------------------------AVANT ---------------------------------------------\n");
  debug_dump($params);
  echo ("----------------------------APRES----------------------------------------------\n");
  //$params = addUUIDKey( $params, $created_uuid);
  debug_dump($params);
  echo ("--------------------------------------------------------------------------\n");

  //


  debug_dump($params, "$$$$$$$$$$$$$$  UPDATE $$$$$$$$$$$$$$$$$$\n");
  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  return $ret;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_command_delete($configuration, $need_sd_connection = false)
{
  if ($need_sd_connection)
  {
    sd_connect();
  }

  //MODIF LO $ret = juniper_contrail_apply_command_delete($configuration, false);
  $created_uuid = "";
  $ret = juniper_contrail_apply_conf($configuration, $created_uuid);
  // FIN MODIF
  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  return $ret;
}

/**
 * Execute a command on a device
 * @param  $cmd
 * @param  $need_sd_connection
 */
function sd_execute_command($cmd, $need_sd_connection = false)
{
  global $sms_sd_ctx;

  if ($need_sd_connection)
  {
    $ret = sd_connect();
    if ($ret !== SMS_OK)
    {
      return false;
    }
  }

  $ret = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd);

  if ($need_sd_connection)
  {
    sd_disconnect(true);
  }

  return $ret;
}

?>