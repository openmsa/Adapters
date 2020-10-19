<?php

// -------------------------------------------------------------------------------------
// 
/*
-	Create the CMS org-networks and associate the CMS instance to CMS Organization

IMPORT TO GET THE NETWORK_LIST
 <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.ubiqube.com">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:generateConfiguration>
         <deviceId>217</deviceId>
         <commandName>IMPORT</commandName>
         <objectParameters>{
           "organizations":"CUST-TEST"
          }</objectParameters>
      </ws:generateConfiguration>
   </soapenv:Body>
 </soapenv:Envelope>
 
 
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.ubiqube.com">
  <soapenv:Header/>
   <soapenv:Body>
      <ws:executeCommand>
         <deviceId>217</deviceId>
         <commandName>UPDATE</commandName>
         <objectParameters>{
        "organizations": {
		   "CUST-TEST": {
			    "object_id" : "CUST-TEST",
				"organization_uuid" :"559a0307900f26-49102074-3245456",
			    "org_network_list" : "{\"ipaddress-allocation-mode\":\"dhcp\", \"name\":\"CUST-TEST\",\"uuid\":\"0d390b14-6287-4c76-adcd-6cef23baff83\"}",
			    "instances_list" : "\"svc-VERSA-test\""
			}
        }
     }</objectParameters>
      </ws:executeCommand>
   </soapenv:Body>
</soapenv:Envelope>

*/
// -------------------------------------------------------------------------------------
function prov_org_networks($sms_csp, $sdid, $sms_sd_info, $stage)
{
  global $tenant_name;
  global $versa_director_deviceId;
  global $appliance_name;
 
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
	$new_instance= array("instance" => "{$appliance_name}");
	array_push($objets['organizations'][$tenant_name]['instances'],$new_instance);
	$arr = $objets['organizations'][$tenant_name]['org_networks'];
	foreach ($arr as $key => $value) {
	  $objets['organizations'][$tenant_name]['org_networks'][$key]= array_merge($objets['organizations'][$tenant_name]['org_networks'][$key],array("ipadddress-allocation-mode"=>"dhcp")); 
	  $objets['organizations'][$tenant_name]['org_networks'][$key] =  array_merge($objets['organizations'][$tenant_name]['org_networks'][$key],array("name"=>"{$tenant_name}")); 
	}
	
	$org_networks_list= str_replace("\"","\\\"",json_encode($objets['organizations'][$tenant_name]['org_networks']));
	$instances_list = str_replace("\"","\\\"",json_encode($objets['organizations'][$tenant_name]['instances']));
	$uuid = $objets['organizations'][$tenant_name]['uuid'];
	
	$data = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ws=\"http://ws.ubiqube.com\"> <soapenv:Header/><soapenv:Body><ws:executeCommand> <deviceId>{$versa_director_deviceId}</deviceId> <commandName>UPDATE</commandName><objectParameters>{\"organizations\": {\"{$tenant_name}\": { \"object_id\" : \"{$tenant_name}\",\"uuid\" :\"{$uuid}\",\"org_network_list\" : \"{$org_networks_list}\",\"instances_list\" : \"{$instances_list}\"}}}</objectParameters></ws:executeCommand></soapenv:Body></soapenv:Envelope>";
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
	
	
  // Set Ip config
  if ($ret != SMS_OK)
  {
    sms_bd_set_provstatus($sms_csp, $sms_sd_info, $stage, 'F', $ret, null, "");
    return $ret;
  }
  return SMS_OK;
}

?>