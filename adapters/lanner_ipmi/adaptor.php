<?php
/*
 * Version: $Id$
 * Created: May 30, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('ipmi_generic', 'ipmi_generic_connect.php');
require_once load_once('ipmi_generic', 'ipmi_generic_apply_conf.php');
require_once load_once('ipmi_generic', 'ipmi_generic_configuration.php');

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null)
{
  return ipmi_generic_connect($login, $passwd);
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect()
{
  return  ipmi_generic_disconnect();
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
    $ret = sd_connect(null, null, null);
  }
  else
  {
    ipmi_generic_synchro_prompt();
  }
  if ($ret != SMS_OK)
  {
  	throw new SmsException("", ERR_SD_CMDTMOUT);
  }

  $ret = ipmi_generic_apply_conf($configuration, false);

  $output = $SMS_OUTPUT_BUF;

  sd_save_conf();

  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  $SMS_OUTPUT_BUF = str_replace("\n", "\\n", $output);

  return $ret;
}


function sd_save_conf()
{
  global $sdid;
  global $sms_sd_ctx;
  $running_conf = "";
  //get and save running conf
  $conf = new ipmi_generic_configuration($sdid);

  $running_conf = $conf->get_running_conf();

  $ret = save_result_file($running_conf, "running.conf");
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

  $ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd);

  if ($need_sd_connection)
  {
    sd_disconnect();
  }

  return $ret;
}

?>
