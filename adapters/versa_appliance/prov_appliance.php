<?php

// -------------------------------------------------------------------------------------
// Deploy the appliance_name
/*
 <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.ubiqube.com">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:executeCommand>
         <deviceId>{$versa_director_deviceId}</deviceId>
         <commandName>CREATE</commandName>
         <objectParameters>{
			"appliance_creation": {
			   "svc-{$hostname}": {
					"appliance_ip" : "{$ipaddr}",
					"object_id" : "svc-{$hostname}",
					"tenant_name" : "{$tenant_name}",
					"applicance_network_name_left" : "LEFT",
					"applicance_network_name_right" : "RIGHT",
					"analytics_name" : "$analytics_name"
				}
			}
		}</objectParameters>
      </ws:executeCommand>
   </soapenv:Body>
</soapenv:Envelope>

*/

// -------------------------------------------------------------------------------------
function prov_appliance($sms_csp, $sdid, $sms_sd_info, $stage)
{
	global $tenant_name;
	global $versa_director_deviceId;
	global $appliance_name;
	global $analytics_name;
	global $ipaddr;
	global $hostname;  
	$data = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\"> <soapenv:Header/> <soapenv:Body> <ws:executeCommand> <deviceId>{$versa_director_deviceId}</deviceId> <commandName>CREATE</commandName> <objectParameters>{ \"appliance_creation\": { \"svc-{$hostname}\": { \"appliance_ip\" : \"{$ipaddr}\", \"object_id\" : \"svc-{$hostname}\", \"tenant_name\" : \"{$tenant_name}\", \"applicance_network_name_left\" : \"LEFT\", \"applicance_network_name_right\" : \"RIGHT\", \"analytics_name\" : \"{$analytics_name}\" } } }</objectParameters> </ws:executeCommand> </soapenv:Body></soapenv:Envelope>";
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
	
	// Chopper le task id en retour pour suivre la création
	$xml = simplexml_load_string($result, NULL, NULL, "http://schemas.xmlsoap.org/soap/envelope/");
    $ns = $xml->getNamespaces(true);
	
    $soap = $xml->children($ns['env']);
    $resultat = $soap->Body->children($ns['ns2']);
	$tasks = $resultat->children();
	 
	$objets = json_decode($tasks->return->message,true);
	$task_id = $objets['output']['result']['task']['task-id'];
    
	// Faire une boucle d'attente du retour avec le task ID
	$task_status = 'PENDING';
	  // wait the device become up after boot
	$done = 10;
	$ret = "SMS_ERROR";
	do
	{
		echo "waiting for the appliance to be deployed, $done. Task status : $task_status\n";
		sleep(5);

		$data = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\"><soapenv:Header/><soapenv:Body><ws:generateConfiguration><deviceId>{$versa_director_deviceId}</deviceId><commandName>IMPORT</commandName><objectParameters>{\"task\":\"{$task_id}\"}</objectParameters></ws:generateConfiguration></soapenv:Body> </soapenv:Envelope>";
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
					break;
				  }
				}
			  }
		}
		
		// Chopper le status  pour suivre la création
		$xml = simplexml_load_string($result, NULL, NULL, "http://schemas.xmlsoap.org/soap/envelope/");
		$ns = $xml->getNamespaces(true);
		
		$soap = $xml->children($ns['env']);
		$resultat = $soap->Body->children($ns['ns2']);
		$tasks = $resultat->children();
		$objets = json_decode($tasks->return,true);
		$task_status = $objets['task'][$task_id]['task_status'];
		if (strpos($task_status, "OK") === 0)
		{
			$ret = SMS_OK;
			break;
		}
		if (strpos($task_status, "FAILED") === 0)
		{
			break;
		}		
		$done--;
	} while ($done > 0);

	//
	 if (($done === 0) || ($ret != SMS_OK))
	{
		$ret = $objets['task'][$task_id]['message'];
		sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
		return $ret;
	}
	return SMS_OK;
}

?>