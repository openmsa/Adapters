<?php

require_once 'smsd/sms_common.php';
require_once 'rest_connection.php';
require_once "$db_objects";

define('MAX_TIME', EXPECT_DELAY / 1000);

class DeviceConnection extends RestConnection
{

  function curl($rest_method, $path, $data)
  {
    global $SMS_RETURN_BUF;
    global $SMS_OUTPUT_BUF;

    $net_profile = get_network_profile();
    $sd = &$net_profile->SD;

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
      if (!empty($sd->SD_CONFIGVAR_list['rest_protocol']) && $sd->SD_CONFIGVAR_list['rest_protocol']->VAR_VALUE === 'https')
      {
        $url = "https://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";
      }
      else
      {
        $url = "http://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";
      }
    }

    $this->setTimeout(EXPECT_DELAY / 1000, MAX_TIME);
    //$this->setHttpHeader(array('Content-Type: application/json'));
    $this->setHttpHeader(array('Content-Type: application/json','Accept: application/json'));
    $this->setAuth($this->sd_login_entry, $this->sd_passwd_entry);

    $ret = $this->exec($rest_method, $url, $data);

    if ($ret->status !== true)
    {
      $SMS_OUTPUT_BUF = "METHOD: " . $rest_method . "\nURL: " . $url . "\nDATA: " . $data;
      throw new SmsException("Call to API Failed", ERR_SD_CMDFAILED);
    }

    sms_log_info("Device response code: " . $ret->http_code);
    sms_log_debug(15, "Device response header: " . $ret->res_header);
    //sms_log_debug(15, print_r($ret->res_header_fields, true));
    sms_log_info("Device response: " . $ret->res_body);

    $jresult = array();
    $jresult['request'] = urldecode(trim($SMS_RETURN_BUF));
    $jres = array();
    $jres['http_code'] = $ret->http_code;
    $jres['message'] = $ret->res_body;
    $jresult['response'] = $jres;

    $SMS_RETURN_BUF = json_encode($jresult);

    // Check device response error if any.
    if ((int) ($ret->http_code / 100) !== 2)
    {
      //$SMS_OUTPUT_BUF = "METHOD: " . $rest_method . "\nURL: " . $url . "\nDATA: " . $data . "\n---------\n";
      //$SMS_OUTPUT_BUF .= "Request detail: " . json_encode($jresult) . "\n---------\n";
      sms_log_error("METHOD: " . $rest_method . "\nURL: " . $url . "\nDATA: " . $data . "\n");
      sms_log_error("Request detail: " . json_encode($jresult) . "\n");
      $SMS_OUTPUT_BUF .= "HTTP response code:" . $ret->http_code . "\n";
      $SMS_OUTPUT_BUF .= "Error detail: " . $SMS_RETURN_BUF;
      throw new SmsException("The server responded with a status of $ret->http_code", ERR_SD_CMDFAILED);
    }

    return $ret->res_body; 
  }

}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function nec_pflow_pfcscapi_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function nec_pflow_pfcscapi_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
