<?php

// -------------------------------------------------------------------------------------
// CHECK IF TENANT EXISTS, CREATE IF NOT
/*

<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.ubiqube.com">
  <soapenv:Header/>
   <soapenv:Body>
      <ws:executeCommand>
         <deviceId>217</deviceId>
         <commandName>CREATE</commandName>
         <objectParameters>{
			"tenant_creation": {
			   "{$tenant_name}": {
					"tenant_name": "{$tenant_name}",
					"organization_uuid": "{$organization_uuid}",
					"subscription_plan": "Default-NextGen-FW-Plan",
					"authentication_connector": "auth-local",
					"organization_NMS_uuid": "{$organization_NMS_uuid}",
					"zone_left": "LEFT",
					"zone_right": "RIGHT",
					"template_name": "IPFIX",
					"analytics_port": "{$analytics_ip_info[1]}",
					"analytics_name": "{$analytics_name}",
					"analytics_routing_instance": "{$analytics_name}1",
					"analytics_ip": "{$analytics_ip_info[0]}",
					"policy_group_name": "DEFAULT",
					"policy_name": "GLOBAL-PBF"
				}
          }
}</objectParameters>
      </ws:executeCommand>
   </soapenv:Body>
</soapenv:Envelope>
*/

// -------------------------------------------------------------------------------------
function prov_tenant_creation($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $tenant_name;
  global $versa_director_deviceId;
  global $appliance_name;
  global $analytics_ip_port;
  global $analytics_name;

  $data = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\"><soapenv:Header/><soapenv:Body><ws:generateConfiguration><deviceId>{$versa_director_deviceId}</deviceId><commandName>IMPORT</commandName><objectParameters>{\"organizations\":\"0\"}</objectParameters></ws:generateConfiguration></soapenv:Body> </soapenv:Envelope>";
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

	$xml = simplexml_load_string($result, NULL, NULL, "http://schemas.xmlsoap.org/soap/envelope/");
	$ns = $xml->getNamespaces(true);
	$soap = $xml->children($ns['env']);
	$resultat = $soap->Body->children($ns['ns2']);
	$objets = json_decode($resultat->children($ns['return']),true);

	$found = false;
	foreach ($objets['organizations'] as $key => $value) {
		if ($key == $tenant_name) {
			echo("{$tenant_name} is found and is already created\n");
			$found=true;
			break;
			}
	}

	if (!$found) { /// CREATE THE TENANT
	    echo(" Tenant {$tenant_name} creation... \n");
		$analytics_ip_info = explode(":", $analytics_ip_port);
		$organization_NMS_uuid = str_replace('.', '-',uniqid('', true));
		$organization_uuid =  str_replace('.', '-',uniqid('', true));
	    $data ="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\"> <soapenv:Header/> <soapenv:Body> <ws:executeCommand> <deviceId>{$versa_director_deviceId}</deviceId> <commandName>CREATE</commandName> <objectParameters>{  \"tenant_creation\": {   \"{$tenant_name}\": {   \"tenant_name\": \"{$tenant_name}\",   \"organization_uuid\": \"{$organization_uuid}\",   \"subscription_plan\": \"Default-NextGen-FW-Plan\",   \"authentication_connector\": \"auth-local\",   \"organization_NMS_uuid\": \"{$organization_NMS_uuid}\",   \"zone_left\": \"LEFT\",   \"zone_right\": \"RIGHT\",   \"template_name\": \"IPFIX\",    \"analytics_port\": \"{$analytics_ip_info[1]}\",   \"analytics_name\": \"{$analytics_name}\",   \"analytics_routing_instance\": \"{$analytics_name}1\",   \"analytics_ip\": \"{$analytics_ip_info[0]}\",   \"policy_group_name\": \"DEFAULT\",   \"policy_name\": \"GLOBAL-PBF\"  } }}</objectParameters> </ws:executeCommand> </soapenv:Body></soapenv:Envelope>";
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
	}


  if ($ret !== SMS_OK)
  {
    sms_set_update_status($sms_csp, $sdid, $ret, 'CONFIGURATION', 'FAILED', '');
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }

  return SMS_OK;
}

?>