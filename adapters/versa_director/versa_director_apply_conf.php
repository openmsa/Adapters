<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('versa_director', 'versa_director_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function versa_director_apply_conf($configuration)
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
  
  echo ("::::::::::::::::::::::::::::::::   APPLY_CONF :::::::::::::::::::: \n");
  
  $line = get_one_line($configuration);
  while ($line !== false)
  {
    $line = trim($line);
    if (!empty($line))
    {
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, trim($line), '//root');
      
      // Si on a un UUID on récupère l'objet, sinon erreur
      // MODIF LO : suppression du IF empty pour le DELETE qui ne renvoie rien
      if (!empty($res))
      {
        $object_type = $res->children();
        $cmd_quote = str_replace("\"", "'", $sms_sd_ctx->get_raw_json());
        $cmd_return = str_replace("\n", "", $cmd_quote);
        echo "OK RECUPE CONFIG : {$cmd_return} \n";
        $SMS_RETURN_BUF = $cmd_return;
      }
      else
      {
        //sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $res  !!!]]\n");
        //return ERR_SD_CMDFAILED;
      }
    }
    $line = get_one_line($configuration);
  }
  return SMS_OK;
}

?>