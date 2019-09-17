<?php

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once ( 'nec_pflow_p4_unc', 'nec_pflow_p4_unc_connect.php' );
require_once load_once ( 'nec_pflow_p4_unc', 'nec_pflow_p4_unc_apply_conf.php' );
require_once load_once ( 'nec_pflow_p4_unc', 'nec_pflow_p4_unc_configuration.php' );

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = nec_pflow_p4_unc_connect($login, $passwd);

  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = nec_pflow_p4_unc_disconnect();

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

        echo "a configuration line found: " . $line . "\n";
        $result = '';

        $pattern_action = '/^([^\&]+)\&xpath=([^\&]+)\&element=(.*)/';
        $preg_match_status = preg_match($pattern_action, $line, $matches);
        if ($preg_match_status === 1)
        {
          $xpr_action  = $matches[1];
          $xpr_xpath   = $matches[2];
          $xpr_element = $matches[3];

          echo "preg_match succedd\n";
          echo "action : " . $xpr_action  . "\n";
          echo "xpath  : " . $xpr_xpath   . "\n";
          echo "element: " . $xpr_element . "\n";

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

            echo "preg_match succedd\n";
            echo "action : " . $xpr_action  . "\n";
            echo "xpath  : " . $xpr_xpath   . "\n";

            $xpr_xpath = urldecode($xpr_xpath);
            $result = $sms_sd_ctx->curl($xpr_action, $xpr_xpath, null);
          }
          else
          {
            echo "preg_match failure on $line\n";
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
    return $e->getCode();
  }

  return SMS_OK;
}

?>
