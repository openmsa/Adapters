<?php

/**
 *
 * Device adaptor
 *
 * Created: Dec 13, 2018
 */

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once ( 'fortinet_jsonapi', 'fortinet_jsonapi_connect.php' );
require_once load_once ( 'fortinet_jsonapi', 'fortinet_jsonapi_apply_conf.php' );
require_once load_once ( 'fortinet_jsonapi', 'fortinet_jsonapi_configuration.php' );

require_once "$db_objects";

define('TASK_WAIT_SLEEP', 1);
define('TASK_WAIT_MAX', 30);

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = device_connect($login, $passwd);

  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = device_disconnect();

  return $ret;
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false)
{
  global $sms_sd_ctx;

  try
  {
    $ret = sd_connect();
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    $line = get_one_line($configuration);
    while ($line !== false)
    {
      $line = trim($line);
      if (!empty($line))
      {
        $xpr_action = "";
        $xpr_xpath = "";
        $xpr_element = "";

        $data = "";

        echo "a configuration line found: " . $line . "\n";
        $result = '';

        $pattern_action = '/^([^\&]+)\&xpath=([^\&]+)\&element=(.*)/';
        $preg_match_status = preg_match($pattern_action, $line, $matches);
        if ($preg_match_status === 1)
        {
          $xpr_action  = $matches[1];
          $xpr_xpath   = $matches[2];
          $xpr_element = $matches[3];

          echo "preg_match succeed\n";
          echo "action : " . $xpr_action  . "\n";
          echo "xpath  : " . $xpr_xpath   . "\n";
          echo "element: " . $xpr_element . "\n";

          $xpr_xpath = urldecode($xpr_xpath);
          $xpr_element = urldecode($xpr_element);


          $params_data = " \"data\" : [ $xpr_element ] ";
          $params_url  = " \"url\" : \"$xpr_xpath\" ";
          $method      = " \"method\" : \"$xpr_action\" ";
          $session     = $sms_sd_ctx->getSession();
          $id          = posix_getpid();

          $data  = "{ {$method} , \"params\" : [ { {$params_url} , {$params_data} } ], ";
          $data .= " \"session\" : \"{$session}\" , ";
          $data .= " \"id\" : {$id} }";

          //$result = $sms_sd_ctx->curl($xpr_action, $xpr_xpath, $xpr_element);
          $result = $sms_sd_ctx->curl('POST', '/jsonrpc/', $data);
        }
        else
        {
          // It's perhaps a delete:
          $pattern_action = '/^([^\&]+)\&xpath=([^\&]+)/';
          $preg_match_status = preg_match($pattern_action, $line, $matches);
          if ( $preg_match_status === 1 )
          {
            $xpr_action  = $matches[1];
            $xpr_xpath   = $matches[2];

            echo "preg_match succeed\n";
            echo "action : " . $xpr_action  . "\n";
            echo "xpath  : " . $xpr_xpath   . "\n";

            $xpr_xpath = urldecode($xpr_xpath);

            $params_url  = " \"url\" : \"$xpr_xpath\" ";
            $method      = " \"method\" : \"$xpr_action\" ";
            $session     = $sms_sd_ctx->getSession();
            $id          = posix_getpid();

            $data  = "{ {$method} , \"params\" : [ { {$params_url} } ], ";
            $data .= " \"session\" : \"{$session}\" , ";
            $data .= " \"id\" : {$id} }";

            //$result = $sms_sd_ctx->curl($xpr_action, $xpr_xpath, null);
            $result = $sms_sd_ctx->curl('POST', '/jsonrpc/', $data);
          }
          else
          {
            echo "preg_match failure on $line\n";
            return ERR_SD_CMDFAILED;
          }
        }
      }

      if ($sms_sd_ctx->getTask() !== null)
      {
        $task_id = $sms_sd_ctx->getTask();
        $params_url  = " \"url\" : \"/task/task/{$task_id}\" ";
        $method      = " \"method\" : \"get\" ";
        $session     = $sms_sd_ctx->getSession();
        $id          = posix_getpid();

        $data  = "{ {$method} , \"params\" : [ { {$params_url} } ], ";
        $data .= " \"session\" : \"{$session}\" , ";
        $data .= " \"id\" : {$id} }";

        $task_progress = 0;
        for ($count = 0; $count < TASK_WAIT_MAX; $count++)
        {
          $result = $sms_sd_ctx->curl('POST', '/jsonrpc/', $data);

          $res_body = json_decode($result, true);
          $task_progress = $res_body['result'][0]['data']['percent'];
          sms_log_debug(15, "Task progress: " . $task_progress);

          if( $task_progress === 100 )
          {
            break;
          }
          sleep(TASK_WAIT_SLEEP);
        }
        $sms_sd_ctx->unsetTask();

        if ($count === TASK_WAIT_MAX)
        {
          echo "task wait timeout\n";
          return ERR_SD_CMDFAILED;
        }
      }

      $line = get_one_line($configuration);
    }

    sd_disconnect();
  }
  catch (Exception $e)
  {
    return $e->getCode();
  }

  return SMS_OK;
}

?>
