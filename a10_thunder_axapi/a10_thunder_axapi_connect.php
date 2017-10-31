<?php

/**
 * A10 Thunder aXAPI
 *
 * Device connection
 *
 * @copyright 2016 NEC Corporation
 */

require_once 'smsd/sms_common.php';
require_once 'rest_connection.php';
require_once "$db_objects";

define('MAX_TIME', 1200);

/**
 * Device connection
 */
class DeviceConnection extends RestConnection
{
  function curl($rest_method, $path, $data)
  {
    global $SMS_RETURN_BUF;

    $net_profile = get_network_profile();
    $sd = &$net_profile->SD;

    if (empty($sd->SD_CONFIGVAR_list['axapi_protocol']))
    {
      $axapi_protocol = 'https';
    }
    else if (strcasecmp($sd->SD_CONFIGVAR_list['axapi_protocol']->VAR_VALUE, 'http') == 0)
    {
      $axapi_protocol = 'http';
    }
    else
    {
      $axapi_protocol = 'https';
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
      $url = "{$axapi_protocol}://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";
    }

    $this->setTimeout(EXPECT_DELAY / 1000, MAX_TIME);
    $this->setHttpHeader(array('Content-Type: application/json'));

    $ret = $this->exec($rest_method, $url, $data);

    if ($ret->status !== true)
    {
      throw new SmsException("Call to API Failed", ERR_SD_CMDFAILED);
    }

    sms_log_info("Device response code: " . $ret->http_code);
    sms_log_debug(15, "Device response header: " . $ret->res_header);
    //sms_log_debug(15, print_r($ret->res_header_fields, true));
    sms_log_info("Device response: " . $ret->res_body);

    // 2xx
    if ((int) ($ret->http_code / 100) === 2)
    {
      $res_body = json_decode($ret->res_body, true);
      // aXAPI always returns HTTP Status Code 200, regardless of whether the aXAPI call is successful or fails.
      // The request fails
      if (!empty($res_body['response']['status']) && $res_body['response']['status'] === 'fail')
      {
        throw new SmsException("The server responded with 200, but the request fails", ERR_SD_CMDFAILED);
      }
      // The request is successful
      else
      {
        $jresult = array();
        $jresult['request'] = urldecode(trim($SMS_RETURN_BUF));

        // CLI module
        if (strpos($jresult['request'], 'method=cli.') !== false)
        {
          // Replace all special marker with the newline
          $jresult['request'] = str_replace("<br>", "\n", $jresult['request']);
        }

        $jres = array();
        $jres['http_code'] = $ret->http_code;
        $jres['message'] = $ret->res_body;
        $jresult['response'] = $jres;

        $SMS_RETURN_BUF = json_encode($jresult);

        return $ret->res_body;
      }
    }
    else
    {
      throw new SmsException("The server responded with a status of $ret->http_code", ERR_SD_CMDFAILED);
    }
  }

}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function a10_thunder_axapi_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function a10_thunder_axapi_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
