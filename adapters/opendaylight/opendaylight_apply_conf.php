<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('opendaylight', 'opendaylight_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function opendaylight_apply_conf($configuration, &$created_uuid)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $operation;
  global $SD;
  
  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');
  $SMS_OUTPUT_BUF = '';
  
  echo ("::::::::::::::::::::::::::::::::   APPLY2 :::::::::::::::::::: \n");
  
  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    
    if (!empty($line))
    {
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, '//root');
      
      // Si on a un UUID on récupère l'objet, sinon erreur
      // MODIF LO : suppression du IF empty pour le DELETE qui ne renvoie rien
      if (!empty($res))
      {
        $object_type = $res->children();
        $created_uuid = $res->xpath('//id');
      }
      
      // FIN MODIF
    }
    $line = get_one_line($configuration);
  }
  
  return SMS_OK;
}
function opendaylight_apply_update($configuration)
{
  global $sdid;
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $apply_errors;
  global $operation;
  global $SD;
  
  // Save the configuration applied on the router
  save_result_file($configuration, 'conf.applied');
  $SMS_OUTPUT_BUF = '';
  
  echo ("::::::::::::::::::::::::::::::::   APPLY UPDATE :::::::::::::::::::: \n");
  
  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    
    if (!empty($line))
    {
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, trim($line), '//root');
    }
    $line = get_one_line($configuration);
  }
  
  return SMS_OK;
}
?>