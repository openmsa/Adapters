<?php

/**
 *
 * Device connection
 *
 * Created: Dec 13, 2018
 */

require_once 'smsd/sms_common.php';

require_once load_once('fortinet_jsonapi', 'rest_connection.php');

require_once "$db_objects";

define('MAX_TIME', 1200);

/**
 * Device connection
 */
class DeviceConnection extends RestConnection
{
  private $session_id;
  private $task_id;

  function curl($json_method, $path, $data)
  {
    global $SMS_RETURN_BUF;

    $net_profile = get_network_profile();
    $sd = &$net_profile->SD;

    if (empty($sd->SD_CONFIGVAR_list['json_protocol']))
    {
      $json_protocol = 'https';
    }
    else if (strcasecmp($sd->SD_CONFIGVAR_list['json_protocol']->VAR_VALUE, 'http') == 0)
    {
      $json_protocol = 'http';
    }
    else
    {
      $json_protocol = 'https';
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
      $url = "{$json_protocol}://{$this->sd_ip_config}:{$this->sd_management_port}{$path}";
    }

    $this->setTimeout(EXPECT_DELAY / 1000, MAX_TIME);
    $this->setHttpHeader(array('Content-Type: application/json'));

    $ret = $this->exec($json_method, $url, $data);

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
      // The request fails
      if (!empty($res_body['result'][0]['status']['message']) && $res_body['result'][0]['status']['message'] !== 'OK')
      {
        throw new SmsException("The server responded with 200, but the request fails", ERR_SD_CMDFAILED);
      }
      // The request is successful
      else
      {
        //$jresult = array();
        //$jresult['request'] = urldecode(trim($SMS_RETURN_BUF));

        //$jres = array();
        //$jres['http_code'] = $ret->http_code;
        //$jres['message'] = $ret->res_body;
        //$jresult['response'] = $jres;

        //$SMS_RETURN_BUF = json_encode($jresult);

        if (!isset($this->session_id))
        {
          $this->session_id = $res_body['session'];
        }

        if (!isset($this->task_id) && !empty($res_body['result'][0]['data']['task']))
        {
          $this->task_id = $res_body['result'][0]['data']['task'];
          sms_log_debug(15, "Task ID: " . $this->task_id);
        }

        return $ret->res_body;
      }
    }
    else
    {
      throw new SmsException("The server responded with a status of $ret->http_code", ERR_SD_CMDFAILED);
    }
  }

  public function do_connect()
  {
    unset($this->session_id);

    $params_data = " \"data\" : [ { \"user\" : \"{$this->sd_login_entry}\", \"passwd\" : \"{$this->sd_passwd_entry}\" } ] ";
    $params_url  = " \"url\" : \"/sys/login/user\" ";
    $method      = " \"method\" : \"exec\" ";
    $id = posix_getpid();

    $data  = "{ {$method} , \"params\" : [ { {$params_url} , {$params_data} } ], ";
    $data .= " \"id\" : {$id} }";

    $result = $this->curl('POST', '/jsonrpc/', $data);
    
    return SMS_OK;
  }

  public function do_disconnect()
  {
    if (isset($this->session_id))
    {
      $params_url  = " \"url\" : \"/sys/logout\" ";
      $method      = " \"method\" : \"exec\" ";
      $id = posix_getpid();

      $data  = "{ {$method} , \"params\" : [ { {$params_url} } ], ";
      $data .= " \"session\" : \"{$this->session_id}\" , ";
      $data .= " \"id\" : {$id} }";

      $result = $this->curl('POST', '/jsonrpc/', $data);
    }
    unset($this->session_id);
  }

  public function getSession()
  {
    return $this->session_id;
  }

  public function getTask()
  {
    return $this->task_id;
  }

  public function unsetTask()
  {
    unset($this->task_id);
  }

}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function device_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new DeviceConnection($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function device_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
