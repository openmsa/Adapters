<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once('juniper_contrail', 'juniper_contrail_connect.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function juniper_contrail_apply_conf($configuration, &$created_uuid)
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
      $res = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, trim($line), '//root');
      
      // Si on a un UUID on récupère l'objet, sinon erreur
      // MODIF LO : suppression du IF empty pour le DELETE qui ne renvoie rien
      if (!empty($res))
      {
        $object_type = $res->children();
        foreach ($res->xpath('//uuid') as $uuid_object)
        {
          $cmd = 'GET#' . $object_type->getName() . '/' . $uuid_object;
          $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd);
          if (!empty($result))
          {
            $cmd_quote = str_replace("\"", "'", $sms_sd_ctx->get_raw_json());
            $cmd_return = str_replace("\n", "", $cmd_quote);
            echo "OK RECUPE CONFIG : {$cmd_return} \n";
            $SMS_RETURN_BUF = $cmd_return;
            $created_uuid = $uuid_object->__toString();
          }
        }
      }
      else
      {
        
        //sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $res  !!!]]\n");
        //return ERR_SD_CMDFAILED;
      }
      
      // FIN MODIF
      

      //   if (trim($res['status']) !== 'success')
      //   {
      /*$line = urldecode($line);
       if (!empty($res->msg->line->line))
       {
       $msg = (String)$res->msg->line->line;
       }
       else if (!empty($res->msg->line))
       {
       $msg = (String)$res->msg->line;
       }
       else if (!empty($res->result->msg))
       {
       $msg = (String)$res->result->msg;
       }
       $SMS_OUTPUT_BUF .= "{$line}\n\n{$msg}\n";
       $SMS_OUTPUT_BUF .= "{$line}\n\n";
       }*/
    }
    $line = get_one_line($configuration);
  }
  
  // commit
  /*
   if ($SD->MOD_ID === 136)
   {
   $cmd = "<commit><partial><vsys><member>{$SD->SD_HOSTNAME}</member></vsys></partial></commit>";
   }
   else
   {
   $cmd = "<commit></commit>";
   }
   $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=commit&cmd='.urlencode($cmd));
   if (!empty($result->result) && !empty($result->result->job))
   {
   $job = $result->result->job;
   
   do
   {
   sleep(2);
   $result = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'type=op&cmd='.urlencode("<show><jobs><id>{$job}</id></jobs></show>"));
   if (!empty($operation) && $result->result->job->status == 'ACT')
   {
   status_progress("progress {$result->result->job->progress}%", $operation);
   }
   } while ($result->result->job->status != 'FIN');
   if (!empty($SMS_OUTPUT_BUF))
   {
   $SMS_OUTPUT_BUF .= $result->result->job->asXml();
   }
   }
   save_result_file($SMS_OUTPUT_BUF, "conf.error");
   if (!empty($SMS_OUTPUT_BUF))
   {
   sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
   return ERR_SD_CMDFAILED;
   }
   */
  return SMS_OK;
}

?>