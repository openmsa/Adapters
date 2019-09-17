<?php

require_once 'smsd/sms_common.php';
require_once 'rest_connection.php';
require_once "$db_objects";

define('MAX_TIME', 1200);
define('DEFAULT_RETRY_MAX', 3);

class DeviceConnection extends RestConnection
{
  function curl($rest_method, $path, $data)
  {
    global $SMS_RETURN_BUF;

    $net_profile = get_network_profile();
    $sd = &$net_profile->SD;

    if (empty($sd->SD_CONFIGVAR_list['unc_protocol']))
    {
      $unc_protocol = 'https';
    }
    else if (strcasecmp($sd->SD_CONFIGVAR_list['unc_protocol']->VAR_VALUE, 'http') == 0)
    {
      $unc_protocol = 'http';
    }
    else
    {
      $unc_protocol = 'https';
    }

    if (! empty($sd->SD_CONFIGVAR_list['unc_retry_max']) && $sd->SD_CONFIGVAR_list['unc_retry_max']->VAR_VALUE > 0)
    {
      $retry_max = $sd->SD_CONFIGVAR_list['unc_retry_max']->VAR_VALUE;
    }
    else
    {
      $retry_max = DEFAULT_RETRY_MAX;
    }

    if (! empty($sd->SD_CONFIGVAR_list['unc_retry_interval']) && $sd->SD_CONFIGVAR_list['unc_retry_interval']->VAR_VALUE > 0)
    {
      $retry_fixed_interval = $sd->SD_CONFIGVAR_list['unc_retry_interval']->VAR_VALUE;
    }

    if ($this->sd_management_port === 443)
    {
      $url = "https://{$this->sd_ip_config}{$path}";
    }
    else if ($this->sd_management_port === 80)
    {
      $url = "http://{$this->sd_ip_config}{$path}";
    }
    else
    {
      $url = "{$unc_protocol}://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";
    }

    $this->setTimeout(EXPECT_DELAY / 1000, MAX_TIME);
    $this->setHttpHeader(array('Content-Type: application/json', "username: {$this->sd_login_entry}", "password: {$this->sd_passwd_entry}"));

    $retry_count = 1;
    do
    {
      $ret = $this->exec($rest_method, $url, $data);

      if ($ret->status !== true)
      {
        throw new SmsException("Call to API Failed", ERR_SD_CMDFAILED);
      }

      sms_log_info("Device response code: " . $ret->http_code);
      sms_log_debug(15, "Device response header: " . $ret->res_header);
      //sms_log_debug(15, print_r($ret->res_header_fields, true));
      sms_log_info("Device response: " . $ret->res_body);

      if (isset($ret->res_header_fields['retry-after']) && $ret->res_header_fields['retry-after'] > 0)
      {
        $retry_interval = $ret->res_header_fields['retry-after'];

        // overwrite value
        if (!empty($retry_fixed_interval))
        {
          $retry_interval = $retry_fixed_interval;
        }
      }
      else
      {
        unset($retry_interval);
      }

      // 503 and header 'Retry-After' exists
      if ($ret->http_code === 503 && isset($retry_interval))
      {
        if ($retry_count < $retry_max)
        {
          $retry_log_msg = "Retrying after $retry_interval seconds... (attempt " . ($retry_count + 1) . ")";
          sms_log_info($retry_log_msg);
          sleep($retry_interval);
        }
      }
      // 2xx
      else if ((int) ($ret->http_code / 100) === 2)
      {
        $jresult = array();
        $jresult['request'] = urldecode(trim($SMS_RETURN_BUF));
        $jres = array();
        $jres['http_code'] = $ret->http_code;
        $jres['message'] = $ret->res_body;
        $jresult['response'] = $jres;

        $SMS_RETURN_BUF = json_encode($jresult);

        return $ret->res_body;
      }
      else
      {
        throw new SmsException("The server responded with a status of $ret->http_code", ERR_SD_CMDFAILED);
      }
    } while ($retry_count++ < $retry_max);

    throw new SmsException("The server responded with a status of $ret->http_code", ERR_SD_CMDFAILED);
  }

}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function nec_pflow_p4_unc_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function nec_pflow_p4_unc_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
