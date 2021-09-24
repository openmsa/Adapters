<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class DeviceConnection extends GenericConnection {
  
	protected $xml_response;
	protected $raw_xml;
	public $http_header_list;
	public $protocol;
	public $auth_mode;
	public $conn_timeout;
	public $fqdn;


	public function __construct($ip = null, $login = null, $passwd = null, $admin_password = null, $port = null)
	{
		$network = get_network_profile();
		$SD = &$network->SD;
		echo("**** port: ".$port);
		$this->sd_ip_config = empty($ip) ? $SD->SD_IP_CONFIG : $ip;
		$this->sd_login_entry = empty($login) ? $SD->SD_LOGIN_ENTRY : $login;
		$this->sd_passwd_entry = empty($passwd) ? $SD->SD_PASSWD_ENTRY : $passwd;
		$this->sd_admin_passwd_entry = empty($admin_password) ? $SD->SD_PASSWD_ADM : $admin_password;
		$this->sd_management_port = empty($port) ? $SD->SD_MANAGEMENT_PORT : $port;

		$this->sd_management_port_fallback = $SD->SD_MANAGEMENT_PORT_FALLBACK;
		$this->sd_conf_isipv6 = empty($SD->SD_CONF_ISIPV6 ) ? '' : $SD->SD_CONF_ISIPV6 ; // SD use IPV6

	}

	public function do_connect() {
	}

	public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true) {
		global $sendexpect_result;
		$this->send ( $origin, $cmd );

		if ($prompt !== 'lire dans sdctx' && ! empty ( $prompt )) {
			$tab [0] = $prompt;
		} else {
			$tab = array ();
		}

		$this->expect ( $origin, $tab );

		if (is_array ( $sendexpect_result )) {
			return $sendexpect_result [0];
		}
		return $sendexpect_result;
	}


	public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result') {
		global $$global_result_name;

		if (! isset ( $this->xml_response )) {
			throw new SmsException ( "cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin );
		}
		$index = 0;
		if (empty ( $tab )) {
			$result = $this->xml_response;
			$$global_result_name = $result;
			return $index;
		}
		foreach ( $tab as $path ) {
			$result = $this->xml_response->xpath ( $path );
			if (($result !== false) && ! empty ( $result )) {
				$$global_result_name = $result;
				return $index;
			}
			$index ++;
		}

		throw new SmsException ( "cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin );
	}
	public function do_store_prompt() {
	}

	public function get_raw_xml() {
		return $this->raw_xml;
	}

	public function send($origin, $azure_cmd) {
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		echo ("send(): azure_cmd = ".$azure_cmd."\n");
		$cmd_list = preg_split('@#@', $azure_cmd, 0, PREG_SPLIT_NO_EMPTY);
		debug_dump ( $cmd_list, "CMD_LIST\n" );

		$http_op = $cmd_list[0];
		$azure_path = "";
		if (count($cmd_list) >1 ) {
			$azure_path = $cmd_list[1];
		}

		$headers = "";
		$auth = "";

		echo("auth_mode= ".$this->auth_mode."\n");
                echo("auth_header= ".$this->auth_header."\n");
		if (isset($this->key)) {
	                echo("key= ".$this->key."\n");
		}

		if (isset($this->key)) {
			$H = trim($this->auth_header);
			$headers .= " -H '{$H} {$this->key}'";
                }

/*		foreach($this->http_header_list as $header) {
			$H = trim($header);
			$headers .= " -H '{$H}'";
		}
*/
//Adding the custom headers for azure based on client id and secret.
//The client id and nsecret needs to be created in Azure
//The config variables "CLIENT_ID" and "CLIENT_SECRET" need to be defined
		if(!isset($this->key)){
			$headers .= " -H 'Content-Type: application/x-www-form-urlencoded'";
			$headers .= " --data-urlencode 'grant_type=client_credentials'";
			$headers .= " --data-urlencode 'client_id={$this->client_id}'";
			$headers .= " --data-urlencode 'resource=https://management.azure.com/'";
			$headers .= " --data-urlencode 'client_secret={$this->client_secret}'";
			
		} else{
			$headers .= " -H 'Content-Type: application/json'";
                        $headers .= " -H 'Accept: application/json'";
		}
		
		$curl_cmd = "curl " . $auth . " -X {$http_op} -sw '\nHTTP_CODE=%{http_code}' {$headers} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$azure_path}'";
		if (count($cmd_list) >2) {
			$azure_payload = $cmd_list[2];
			$curl_cmd .= " -d ";
			$curl_cmd .= "'{$azure_payload}'";
		}
		$curl_cmd .= " && echo";

		$this->execute_curl_command ( $origin, $azure_cmd, $curl_cmd  );
	}

	protected function execute_curl_command($origin, $azure_cmd, $curl_cmd) {
		$ret = exec_local ( $origin, $curl_cmd, $output_array );
		if ($ret !== SMS_OK) {
			throw new SmsException ( "Call to API Failed", $ret );
		}

		$result = '';
		foreach ( $output_array as $line ) {
			if ($line !== 'SMS_OK') {
				if (strpos ( $line, 'HTTP_CODE' ) !== 0) {
					$result .= "{$line}\n";
				} else {
					if (strpos ( $line, 'HTTP_CODE=20' ) !== 0) {
						$cmd_quote = str_replace ( "\"", "'", $result );
						$cmd_return = str_replace ( "\n", "", $cmd_quote );
						throw new SmsException ( "$origin: Call to API {$azure_cmd} Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED );
					}
				}
			}
		}
		$result=preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result);
		$array = json_decode ( $result, true );
		if (isset ( $array ['sid'] )) {
			$this->key = $array ['sid'];
		}
		// call array to xml conversion function
		$xml = arrayToXml ( $array, '<root></root>' );
		$this->xml_response = $xml; // new SimpleXMLElement($result);
		$this->raw_json = $result;

		$this->raw_xml = $this->xml_response->asXML ();
		debug_dump ( $this->raw_xml, "DEVICE RESPONSE\n" );
	}

}

class GenericBASICConnection extends DeviceConnection {

	public function do_connect() {
	}

}

class TokenConnection extends DeviceConnection {

	public $sign_in_req_path;
//	public $token_xpath = '//root/token';
	public $auth_header;
	public $key;
	public $tenant_id;
	public $client_id;
	public $client_secret;
	
	public function do_connect() {
		$cmd = "POST#https://login.microsoftonline.com/{$this->tenant_id}/oauth2/token";
		$result = $this->sendexpectone ( __FILE__ . ':' . __LINE__, $cmd );
		debug_dump("//root/access_token", "do_connect result: \n");
		// extract token
		$this->key = (string)($result->xpath("//root/access_token")[0]);
		debug_dump($this->key, "TOKEN\n");
	}
}

// return false if error, true if ok
function azure_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $model_data;

	//$data = json_decode (trim($model_data), true );

	$network = get_network_profile();
	$sd = &$network->SD;
	//debug_dump($sd, "SD\n");

	//debug_dump($sd->SD_CONFIGVAR_list, "SD_CONFIGVAR_list\n");
	//debug_dump($sd->SD_CONFIGVAR_list['AUTH_MODE'], "AUTH_MODE\n");

	$class = "TokenConnection";
        echo  "azure_generic_connect: setting authentication mode to: {$auth_mode}\n";

/*	if (isset($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT'])) {
                $port_to_use = trim($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT']->VAR_VALUE);
                echo "azure_generic_connect: using management port: " . $port_to_use . "\n";
	}
*/

	if (isset($sd->SD_CONFIGVAR_list['CLIENT_ID'])) {
                $client_id = trim($sd->SD_CONFIGVAR_list['CLIENT_ID']->VAR_VALUE);
                echo "azure_generic_connect: using client_id: " . $client_id . "\n";
        }
	if (isset($sd->SD_CONFIGVAR_list['CLIENT_SECRET'])) {
                $client_secret = trim($sd->SD_CONFIGVAR_list['CLIENT_SECRET']->VAR_VALUE);
                echo "azure_generic_connect: using client secret: " . $client_secret . "\n";
        }

        if (isset($sd->SD_CONFIGVAR_list['TENANT_ID'])) {
                $tenant_id = trim($sd->SD_CONFIGVAR_list['TENANT_ID']->VAR_VALUE);
                echo "azure_generic_connect: using tenant id: " . $tenant_id . "\n";
        }


	echo "azure_generic_connect: using connection class: " . $class . "\n";
	$sms_sd_ctx = new $class ( $sd_ip_addr, $login, $passwd, "", $port_to_use );
	$sms_sd_ctx->client_id = $client_id;
	$sms_sd_ctx->client_secret=$client_secret;
	$sms_sd_ctx->tenant_id=$tenant_id;

	if (!isset($sd->SD_CONFIGVAR_list['AUTH_HEADER'])) {
		throw new SmsException ( __FILE__ . ':' . __LINE__." missing value for config var AUTH_HEADER" , ERR_SD_CMDFAILED);
	}
	$sms_sd_ctx->auth_header = $sd->SD_CONFIGVAR_list['AUTH_HEADER']->VAR_VALUE;
	echo  "azure_generic_connect: setting authentication header to: {$sms_sd_ctx->auth_header}\n";

	$http_header_str ="Content-Type: application/json | Accept: application/json";
/*	if (isset($sd->SD_CONFIGVAR_list['HTTP_HEADER'])) {
		$http_header_str = $sd->SD_CONFIGVAR_list['HTTP_HEADER']->VAR_VALUE;
		$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	}
*/
	$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	echo "azure_generic_connect: setting HTTP header to: ".print_r($sms_sd_ctx->http_header_list, true)."\n";


	$sms_sd_ctx->conn_timeout = EXPECT_DELAY / 1000;
	if (isset($sd->SD_CONFIGVAR_list['CONN_TIMEOUT'])) {
		$sms_sd_ctx->conn_timeout=trim($sd->SD_CONFIGVAR_list['CONN_TIMEOUT']->VAR_VALUE);
	}
	echo  "azure_generic_connect: setting HTTP timeout to: {$sms_sd_ctx->conn_timeout}\n";

	try
	{
		$sms_sd_ctx->do_connect();
	}
	catch (SmsException $e)
	{
		$sms_sd_ctx->disconnect();
		azure_generic_disconnect();
		throw new SmsException($e->getMessage(), $e->getCode());
	}


	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function azure_generic_disconnect() {
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}


function generate_random_string($length = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }

    return $randomString;
}


function generate_auth($apiSecret, $apiKey)
{
    $nonce = generate_random_string(8);
    $timestamp = gmdate("Ymd\ZHis");
    $hash = hash('sha256', $apiSecret.$timestamp.$nonce);

    return $apiKey.':'.$timestamp.':'.$nonce.':'.$hash;
}
?>
