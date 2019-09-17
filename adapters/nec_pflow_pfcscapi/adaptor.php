<?php

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_connect.php');
require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_apply_conf.php');
require_once load_once('nec_pflow_pfcscapi', 'nec_pflow_pfcscapi_configuration.php');

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = nec_pflow_pfcscapi_connect($login, $passwd);

  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = nec_pflow_pfcscapi_disconnect();

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
  global $SMS_RETURN_BUF;

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

        sms_log_info("a configuration line was found: " . $line);
        $result = '';

        $pattern_action = '/^([^\&]+)\&xpath=([^\&]+)\&element=(.*)/';
        $preg_match_status = preg_match($pattern_action, $line, $matches);
        if ($preg_match_status === 1)
        {
          $xpr_action  = $matches[1];
          $xpr_xpath   = $matches[2];
          $xpr_element = $matches[3];

          sms_log_info("xpath & element preg_match succeeded");
          sms_log_debug(15, "action : " . $xpr_action);
          sms_log_debug(15, "xpath  : " . $xpr_xpath);
          sms_log_debug(15, "element: " . $xpr_element);

          $xpr_xpath = urldecode($xpr_xpath);
          $xpr_element = urldecode($xpr_element);
          $result = $sms_sd_ctx->curl($xpr_action, $xpr_xpath, $xpr_element);
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

            sms_log_info("xpath only preg_match succeeded");
            sms_log_debug(15, "action : " . $xpr_action);
            sms_log_debug(15, "xpath  : " . $xpr_xpath);

            $xpr_xpath = urldecode($xpr_xpath);
            $result = $sms_sd_ctx->curl($xpr_action, $xpr_xpath, null);
          }
          else
          {
            sms_log_error("preg_match failed on " . $line);
            return ERR_SD_CMDFAILED;
          }
        }

        // TODO: analyse result.
      }

      $line = get_one_line($configuration);
    }

    sd_disconnect();
  }
  catch (Exception $e)
  {
    //$SMS_RETURN_BUF = "test";
    //$SMS_OUTPUT_BUF = $SMS_RETURN_BUF;
    // $SMS_OUTPUT_BUF must set by the THROWer
    save_result_file($SMS_RETURN_BUF, "conf.error");
    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_RETURN_BUF !!!]]\n");

    return $e->getCode();
  }

  return SMS_OK;
}

?>
