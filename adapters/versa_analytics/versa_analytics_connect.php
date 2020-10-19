<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class PaloAltoGenericsshConnection extends GenericConnection
{
  private $key;
  private $xml_response;
  private $raw_xml;

  // ------------------------------------------------------------------------------------------------
  public function do_connect()
  {
/*    unset($this->key);
    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, "type=keygen&user={$this->sd_login_entry}&password={$this->sd_passwd_entry}", "result/key");
    $this->key = $result;
    echo "{$this->key}\n";*/
    unset($this->key);
    unset($this->endPointsURL);

    // first time keystone is forced by removing the endpoint
    unset($this->endPoint);

    $cmd = "POST##:9183/auth/token#{
	\"username\":\"{$this->sd_login_entry}\",
	\"client_secret\":\"asrevnet_123\",
	\"grant_type\":\"password\",
	\"client_id\":\"voae_rest\",
	\"password\":\"{$this->sd_passwd_entry}\"
	}";

    $result = $this->sendexpectone(__FILE__ . ':' . __LINE__, $cmd, "");
    $token = $result->xpath('//token/id');
    $this->key = $token[0];

    $endPointsURL_table = $result->xpath('//serviceCatalog');
    $this->endPointsURL = $endPointsURL_table[0];

  }

  // ------------------------------------------------------------------------------------------------
  public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
  {
    global $sendexpect_result;
    $this->send($origin, $cmd);

    if ($prompt !== 'lire dans sdctx' && !empty($prompt))
    {
      $tab[0] = $prompt;
    }
    else
    {
      $tab = array();
    }


    $this->expect($origin, $tab);

    if (is_array($sendexpect_result))
    {
      return $sendexpect_result[0];
    }
    return $sendexpect_result;
  }

  // ------------------------------------------------------------------------------------------------
  public function send($origin, $cmd)
  {
    unset($this->xml_response);
    unset($this->raw_xml);

    $delay = EXPECT_DELAY / 1000;
    $curl_cmd = "curl -XPOST --connect-timeout {$delay} --max-time {$delay} -k 'https://{$this->sd_ip_config}/api/?{$cmd}";
    if (isset($this->key))
    {
      $curl_cmd .= "&key={$this->key}";
    }
    $curl_cmd .= "' && echo";
    $ret = exec_local($origin, $curl_cmd, $output_array);
    if ($ret !== SMS_OK)
    {
      throw new SmsException("Call to API Failed", $ret);
    }

    $result = '';
    foreach ($output_array as $line)
    {
      if ($line !== 'SMS_OK')
      {
        $result .= "{$line}\n";
      }
    }

    $this->xml_response = new SimpleXMLElement($result);
    $this->raw_xml = $this->xml_response->asXML();
    debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
  }

  // ------------------------------------------------------------------------------------------------
  public function sendCmd($origin, $cmd)
  {
    $this->send($origin, $cmd);
  }

  // ------------------------------------------------------------------------------------------------
  public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result')
  {
    global $$global_result_name;

    if (!isset($this->xml_response))
    {
      throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
    }
    $index = 0;
    if (empty($tab))
    {
      $result = $this->xml_response;
      $$global_result_name = $result;
      return $index;
    }
    foreach ($tab as $path)
    {
      $result = $this->xml_response->xpath($path);
      if (($result !== false) && !empty($result))
      {
        $$global_result_name = $result;
        return $index;
      }
      $index++;
    }

    throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
  }

  // ------------------------------------------------------------------------------------------------
  public function do_store_prompt()
  {
  }

  public function get_raw_xml()
  {
    return $this->raw_xml;
  }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function versa_analytics_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new sshConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function versa_analytics_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>