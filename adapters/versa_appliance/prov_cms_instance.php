<?php

// -------------------------------------------------------------------------------------
// LOCK PROVISIONING
// -------------------------------------------------------------------------------------
function prov_cms_instance($sms_csp, $sdid, $sms_sd_info, $stage)
{

  global $ipaddr;
  global $hostname;
  global $versa_director_deviceId;
  
  $data = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\">   <soapenv:Header/>   <soapenv:Body>      <ws:executeCommand>         <deviceId>{$versa_director_deviceId}</deviceId>         <commandName>CREATE</commandName>         <objectParameters>{        \"instances\": {          \"{]}\": {     \"ip_address\" : \"{$ipaddr}\",      \"object_id\" : \"{$hostname}\"    }     }  }  </objectParameters>    </ws:executeCommand>   </soapenv:Body></soapenv:Envelope>";
  $curl_cmd = "curl  -sw '\nHTTP_CODE=%{http_code}' -u ncroot:ubiqube -X POST  -H 'Content-Type: text/xml' --connect-timeout 50 --max-time 50 -d '{$data}' -k 'http://127.0.0.1/webapi/OrderCommandWS?wsdl' && echo";
  $ret = exec_local(__FILE__ . ':' . __LINE__, $curl_cmd, $output_array);
  $result = '';
  foreach ($output_array as $line)
    {
	  $line = trim($line);
      if ($line !== 'SMS_OK')
      {
        if (strpos($line, 'HTTP_CODE') !== 0)
        {
          $result .= "{$line}\n";
        }
        else
        {
          if (strpos($line, 'HTTP_CODE=200') !== 0)
          {
		    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
            $ret ="$origin: Call to API Failed HTTP CODE = $line, $cmd_quote error";
          }
        }
      }
    }
	
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }
  
  return SMS_OK;
}

?>



