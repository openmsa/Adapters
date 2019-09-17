<?php

require_once 'smsd/generic_connection.php';

class RestData
{
  public $status;
  public $message;
  public $http_code;
  public $req_header;
  public $res_header;
  public $res_body;
  public $res_header_fields;
}

class RestConnection extends GenericConnection
{
  protected $connect_timeout = 300;
  protected $timeout = 0;
  protected $http_header = null;
  protected $userpwd = null;
  protected $ssl_version = 1; // CURL_SSLVERSION_TLSv1 (1)

  public function setTimeout($conn_timeout = null, $timeout = null)
  {
    if (!empty($conn_timeout))
    {
      $this->connect_timeout = $conn_timeout;
    }
    if (!empty($timeout))
    {
      $this->timeout = $timeout;
    }
  }

  public function setHttpHeader($header = null)
  {
    if (is_array($header))
    {
      $this->http_header = $header;
    }
    else
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ':' . __FUNCTION__ . ": value is not array");
    }

    return $this->http_header;
  }

  public function setAuth($user = '', $password = '')
  {
    $this->userpwd = $user . ':' . $password;
  }

  public function setSslVersion($version = 0)
  {
    $this->ssl_version = $version;
  }

  public function exec($method = 'GET', $url = null, $data = null)
  {
    $ch = curl_init();
 
    $options = array(
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_URL => $url,
      CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_HEADER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLINFO_HEADER_OUT => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSLVERSION => $this->ssl_version
    );
    if (!empty($this->http_header))
    {
      $options[CURLOPT_HTTPHEADER] = $this->http_header;
    }
    if (!empty($this->userpwd))
    {
      $options[CURLOPT_USERPWD] = $this->userpwd;
    }

    if (!empty($data))
    {
      $options[CURLOPT_POSTFIELDS] = $data;
    }

    curl_setopt_array($ch, $options);

    sms_log_info('curl options: ' . print_r($options, true));
 
    // send request to the device
    $res = curl_exec($ch);

    echo 'curl_exec duration ' . curl_getinfo($ch, CURLINFO_TOTAL_TIME) . " s\n";
 
    $func_ret = new RestData();
    $func_ret->req_header = trim(curl_getinfo($ch, CURLINFO_HEADER_OUT));
 
    if (curl_errno($ch) > 0)
    {
      sms_log_error('curl error: ' . curl_error($ch));

      $func_ret->status = false;
      $func_ret->message = curl_error($ch);
    }
    else
    {
      $func_ret->status = true;
      $func_ret->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      $func_ret->res_header = trim(substr($res, 0, $header_size));
      $func_ret->res_body = trim(substr($res, $header_size));
      $func_ret->res_header_fields = $this->http_parse_headers($func_ret->res_header);
    }

    curl_close($ch);
 
    return $func_ret;
  }

  public function do_connect()
  {
    // do nothing
  }

  public function do_disconnect()
  {
    // do nothing
  }

  // http://us2.php.net/manual/pl/function.http-parse-headers.php
  // modified by NEC (adding array_change_key_case())
  private function http_parse_headers($raw_headers)
  {
    $headers = array();
    $key = '';

    foreach(explode("\n", $raw_headers) as $i => $h)
    {
      $h = explode(':', $h, 2);

      if (isset($h[1]))
      {
        if (!isset($headers[$h[0]]))
        {
          $headers[$h[0]] = trim($h[1]);
        }
        elseif (is_array($headers[$h[0]]))
        {
          $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
        }
        else
        {
          $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
        }

        $key = $h[0];
      }
      else
      {
        if (substr($h[0], 0, 1) == "\t")
        {
          $headers[$key] .= "\r\n\t".trim($h[0]);
        }
        elseif (!$key)
        {
          $headers[0] = trim($h[0]);
        }
      }
    }

    return array_change_key_case($headers); // modified by NEC
  }
}

?>
