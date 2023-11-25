<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class DeviceConnection extends GenericConnection {

	protected $response; // either SimpleXMLElement or array, depending of rest_json
	public $http_header_list;
	public $protocol;
	public $auth_mode;
	public $auth_header;
	public $conn_timeout;
	public $fqdn;
	public $aws_sigv4;
	public $rest_json;
	public $json_path;

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

		if (!$this->rest_json && is_array ( $sendexpect_result )) {
			return $sendexpect_result [0];
		}
		return $sendexpect_result;
	}


	public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result') {
		global $$global_result_name;

        if (!isset($this->response)) {
            throw new SmsException ( "cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin );
        }
		$index = 0;
		if (empty ( $tab )) {
		    $$global_result_name = $this->response;
			return $index;
		}
		foreach ( $tab as $path ) {
		    if ($this->rest_json) {
                $result = $this->json_path->find($this->response, $path);
		    } else {
		        $result = $this->response->xpath($path);
		    }
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

	public function send($origin, $rest_cmd) {
		unset ( $this->response );
		echo ("send(): rest_cmd = ".$rest_cmd."\n");
		$cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);
		debug_dump ( $cmd_list, "CMD_LIST\n" );

		$http_op = $cmd_list[0];
		$rest_path = "";
		if (count($cmd_list) >1 ) {
			$rest_path = $cmd_list[1];
		}

		$headers = "";
		$auth = "";
		$headers_new = "";
		$auth_new = "";
		$aws_sigv4_new = "";

		echo("auth_mode= ".$this->auth_mode."\n");
                if (isset($this->auth_header)) {
                        echo("auth_header= ".$this->auth_header."\n");
                }
		if (isset($this->key)) {
	                echo("key= ".$this->key."\n");
		}

		if ($this->auth_mode == "BASIC") {
			$auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
			$auth_new =  $this->sd_login_entry . ":" . $this->sd_passwd_entry;
			echo "Auth values $auth_new";
		} else if (($this->auth_mode == "token" || $this->auth_mode == "auth-key") && isset($this->key)) {
			$H = trim($this->auth_header);
			$headers .= " -H '{$H} {$this->key}'";
			$headers_new .= "'{$H} {$this->key}'";

		//	echo ("send(): headers= {$headers}\n");
		// https://tools.ietf.org/html/rfc6750
		} else if (($this->auth_mode == "oauth_v2" || $this->auth_mode == "jns_api_v2") && isset($this->key)) {
                        $H = trim($this->auth_header);
                        $headers .= " -H '{$H} {$this->key}'";
						$headers_new .= "'{$H} {$this->key}'";
						
		} else if (($this->auth_mode == "oauth_v2" || $this->auth_mode == "jns_api_v2") && !isset($this->key)){
                        $auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
						$auth_new =  $this->sd_login_entry . ":" . $this->sd_passwd_entry;
						

                }

		foreach($this->http_header_list as $header) {
			$H = trim($header);
			$headers .= " -H '{$H}'";
			$headers_new .= "'{$H}'";
		}

		if(isset($this->fqdn))
		{
			$ip_address = $this->fqdn;
		}
		else
		{
			$ip_address = $this->sd_ip_config.":".$this->sd_management_port;
		}

		$aws_sigv4="";
		if (isset($this->aws_sigv4)) {
			$aws_sigv4=" --aws-sigv4 '".$this->aws_sigv4."' ";
			$aws_sigv4_new= $this->aws_sigv4;
		}

		//$curl_cmd = "curl " . $auth . " -X {$http_op} -sw '\nHTTP_CODE=%{http_code}' {$headers} {$aws_sigv4} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$this->protocol}://{$ip_address}{$rest_path}'";
		$timestamp = time();
		echo "time $timestamp";
		$ch = curl_init();
		$url = "{$this->protocol}://{$ip_address}{$rest_path}";
		//$url = "https://api.eu-west-2.outscale.com/api/v1/CheckAuthentication";
		//echo $url
		echo "Headers $headers_new";
		//echo "AWS $aws_sigv4"
		echo "url $url"
		//echo "http $http_op"
		curl_setopt($ch, CURLOPT_URL, $url );
		curl_setopt($ch, CURLOPT_USERPWD, $auth_new);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_op);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array($headers_new));
		curl_setopt($ch, CURLOPT_AWS_SIGV4, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 50);
		curl_setopt($ch, CURLOPT_TIMEOUT, 50 );
		if (count($cmd_list) >2 ) {
			$rest_payload = $cmd_list[2];
			curl_setopt($ch, CURLOPT_POSTFIELDS, $rest_payload);
		}

		//$ret = curl_exec($ch);
		//$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//return $httpCode
		$this->execute_curl_command ( $origin, $rest_cmd, $ch );
	}

	protected function execute_curl_command($origin, $rest_cmd, $ch) {
		// $ret = exec_local ( $origin, $curl_cmd, $output_array );
		$ret = curl_exec($ch);
		$info = curl_getinfo($ch);
		echo "Using print_r():\n";
		print_r($info);
		// foreach ($info as $value) {
		// 	echo $value . "\n";
		// }
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($ret !== SMS_OK) {
			throw new SmsException ( "Call to API Failed $info", $ret );
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
						throw new SmsException ( "$origin: Call to API {$rest_cmd} Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED );
					}
				}
			}
		}

		$result = preg_replace('/xmlns="[^"]+"/', '', $result);
		if (strpos($ch, "Content-Type: application/json")) {
	        $result=preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result);
			$array = json_decode ( $result, true );
			if (isset ( $array ['sid'] )) {
				$this->key = $array ['sid'];
			}
			if ($this->rest_json) {
                $response = $array;
			} else {
                // call array to xml conversion function
                $response = arrayToXml ($array, '<root></root>');
			}
		} else {
		    if ($this->rest_json) {
		        throw new SmsException ("$origin: Repsonse to API {$rest_cmd} Failed, expected json received $result", ERR_SD_CMDFAILED );
		    }
		    if (empty(trim($result))) {
		        $response = new SimpleXMLElement('<root></root>');
		    }
		}
        $this->response = $response;

		debug_dump(($this->rest_json) ? $this->response : $this->response->asXML(), "DEVICE RESPONSE\n");
	}

}



class GenericBASICConnection extends DeviceConnection {

	public function do_connect() {
	}

}

class TokenConnection extends DeviceConnection {

	public $sign_in_req_path;
	public $token_xpath = '//root/token';
	public $token_jsonpath = '$.token';
	public $auth_header;
	public $key;

	public function do_connect() {

		$data = "";

		if($this->auth_mode != "auth-key")
		{
			unset ( $this->key );
      
			$network = get_network_profile();
			$sd = &$network->SD;
			if (isset($sd->SD_CONFIGVAR_list['USERNAME_KEY'])) {
			  $username_key = $sd->SD_CONFIGVAR_list['USERNAME_KEY']->VAR_VALUE;
			}else{
			  $username_key =  "username";
			}  
			if (isset($sd->SD_CONFIGVAR_list['PASSWORD_KEY'])) {
			  $password_key = $sd->SD_CONFIGVAR_list['PASSWORD_KEY']->VAR_VALUE;
			}else{
			  $password_key =  "password";
			}  
			
			if($this->auth_mode == "oauth_v2" || $this->auth_mode == "jns_api_v2")
			{
				$data = array (
						"grant_type"  => "password",
						$username_key => $this->sd_login_entry,
						$password_key => $this->sd_passwd_entry
				);
			}
			else
			{
				$data = array (
						$username_key => $this->sd_login_entry,
						$password_key => $this->sd_passwd_entry
				);
			}

			$data = json_encode ( $data );
			$cmd = "POST#{$this->sign_in_req_path}#{$data}";
			$result = $this->sendexpectone ( __FILE__ . ':' . __LINE__, $cmd );
			debug_dump($this->rest_json ? $this->json_path : $this->token_xpath, "do_connect result: \n");

			// extract token
			if ($this->rest_json) {
				$this->key = (string)($this->json_path->find($result, $this->token_jsonpath)[0]);
			} else {
				$this->key = (string)($result->xpath($this->token_xpath)[0]);
			}

        }
		debug_dump($this->key, "TOKEN\n");
	}
}

// return false if error, true if ok
function rest_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $model_data;

	//$data = json_decode (trim($model_data), true );

	$network = get_network_profile();
	$sd = &$network->SD;
	//debug_dump($sd, "SD\n");

	//debug_dump($sd->SD_CONFIGVAR_list, "SD_CONFIGVAR_list\n");
	//debug_dump($sd->SD_CONFIGVAR_list['AUTH_MODE'], "AUTH_MODE\n");

	$class = "GenericBASICConnection";
	$auth_mode = "BASIC";

	if (isset($sd->SD_CONFIGVAR_list['AUTH_MODE'])) {
		$auth_mode = trim($sd->SD_CONFIGVAR_list['AUTH_MODE']->VAR_VALUE);
		if ($auth_mode == "token"
			|| $auth_mode == "auth-key"
			|| $auth_mode == "oauth_v2"
			|| $auth_mode == "jns_api_v2") {
			$class = "TokenConnection";
		}

	}
        echo  "rest_generic_connect: setting authentication mode to: {$auth_mode}\n";

	if (isset($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT'])) {
                $port_to_use = trim($sd->SD_CONFIGVAR_list['MANAGEMENT_PORT']->VAR_VALUE);
                echo "rest_generic_connect: using management port: " . $port_to_use . "\n";
	}

	echo "rest_generic_connect: using connection class: " . $class . "\n";
	$sms_sd_ctx = new $class ( $sd_ip_addr, $login, $passwd, "", $port_to_use );

  	$sms_sd_ctx->auth_mode = $auth_mode;
  	if (isset($sd->SD_CONFIGVAR_list['AUTH_FQDN'])) {
        $fqdn = trim($sd->SD_CONFIGVAR_list['AUTH_FQDN']->VAR_VALUE);
                $sms_sd_ctx->fqdn = $fqdn;
    }
        if (isset($sd->SD_CONFIGVAR_list['TOKEN_XPATH'])) {
        	$token_xpath = trim($sd->SD_CONFIGVAR_list['TOKEN_XPATH']->VAR_VALUE);
  		$sms_sd_ctx->token_xpath = $token_xpath;
    	}

        if (isset($sd->SD_CONFIGVAR_list['TOKEN_JSONPATH'])) {
        	$token_jsonpath = trim($sd->SD_CONFIGVAR_list['TOKEN_JSONPATH']->VAR_VALUE);
  		$sms_sd_ctx->token_jsonpath = $token_jsonpath;
    	}

		

	if ($sms_sd_ctx->auth_mode == "token"
		|| $sms_sd_ctx->auth_mode == "auth-key"
		|| $sms_sd_ctx->auth_mode == "oauth_v2"
		|| $sms_sd_ctx->auth_mode == "jns_api_v2") {

	    if (isset($sd->SD_CONFIGVAR_list['AUTH_KEY'])) {
    		$key = trim($sd->SD_CONFIGVAR_list['AUTH_KEY']->VAR_VALUE);
       		$sms_sd_ctx->key = $key;
   	   	}
   	    echo  "rest_generic_connect: setting AUTH_KEY to: {$sms_sd_ctx->key}\n";

		if (!isset($sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH'])) {
			throw new SmsException ( __FILE__ . ':' . __LINE__." missing value for config var SIGNIN_REQ_PATH" , ERR_SD_CMDFAILED);
		}
		$sms_sd_ctx->sign_in_req_path = $sd->SD_CONFIGVAR_list['SIGNIN_REQ_PATH']->VAR_VALUE;
		echo  "rest_generic_connect: setting SIGNIN_REQ_PATH to: {$sms_sd_ctx->sign_in_req_path}\n";

		if (!isset($sd->SD_CONFIGVAR_list['AUTH_HEADER'])) {
			throw new SmsException ( __FILE__ . ':' . __LINE__." missing value for config var AUTH_HEADER" , ERR_SD_CMDFAILED);
		}
		$sms_sd_ctx->auth_header = $sd->SD_CONFIGVAR_list['AUTH_HEADER']->VAR_VALUE;
		echo  "rest_generic_connect: setting authentication header to: {$sms_sd_ctx->auth_header}\n";
	}

	if (isset($sd->SD_CONFIGVAR_list['HTTP_HEADER'])) {
		$http_header_str = $sd->SD_CONFIGVAR_list['HTTP_HEADER']->VAR_VALUE;
	} else {
        $http_header_str ="Content-Type: application/json | Accept: application/json";
	}
	$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	echo "rest_generic_connect: setting HTTP header to: ".print_r($sms_sd_ctx->http_header_list, true)."\n";

	$sms_sd_ctx->protocol = "https";
	if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
		$sms_sd_ctx->protocol=trim($sd->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE);
	}
	echo  "rest_generic_connect: setting HTTP protocol to: {$sms_sd_ctx->protocol}\n";

	$sms_sd_ctx->conn_timeout = EXPECT_DELAY / 1000;
	if (isset($sd->SD_CONFIGVAR_list['CONN_TIMEOUT'])) {
		$sms_sd_ctx->conn_timeout=trim($sd->SD_CONFIGVAR_list['CONN_TIMEOUT']->VAR_VALUE);
	}
	echo  "rest_generic_connect: setting HTTP timeout to: {$sms_sd_ctx->conn_timeout}\n";

	if (isset($sd->SD_CONFIGVAR_list['AWS_SIGV4'])) {
		$sms_sd_ctx->aws_sigv4=trim($sd->SD_CONFIGVAR_list['AWS_SIGV4']->VAR_VALUE);
		echo  "rest_generic_connect: setting AWS_SIGV4: {$sms_sd_ctx->aws_sigv4}\n";
	}

	if (isset($sd->SD_CONFIGVAR_list['REST_JSON'])) {
	  $sms_sd_ctx->rest_json=trim($sd->SD_CONFIGVAR_list['REST_JSON']->VAR_VALUE);
	  $sms_sd_ctx->json_path = new \JsonPath\JsonPath();
	  echo  "rest_generic_connect: setting REST_JSON: {$sms_sd_ctx->rest_json}\n";
	}

	try
	{
		$sms_sd_ctx->do_connect();
	}
	catch (SmsException $e)
	{
		$sms_sd_ctx->disconnect();
		rest_generic_disconnect();
		throw new SmsException($e->getMessage(), $e->getCode());
	}

	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function rest_generic_disconnect() {
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
