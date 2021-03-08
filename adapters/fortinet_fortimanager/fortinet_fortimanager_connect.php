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

	public function send($origin, $rest_cmd) {
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		$cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);
		$http_op = $cmd_list[0];
		$rest_path = "";
		if (count($cmd_list) >1 ) {
			$rest_path = $cmd_list[1];
		}

		$headers = "";
		$auth = "";

		echo("auth_mode= ".$this->auth_mode."\n");
                echo("auth_header= ".$this->auth_header."\n");
		if (isset($this->key)) {
	                echo("key= ".$this->key."\n");
		}

		if ($this->auth_mode == "BASIC") {
			$auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
		} else if (($this->auth_mode == "token" || $this->auth_mode == "auth-key") && isset($this->key)) {
			$H = trim($this->auth_header);
			$headers .= " -H '{$H}: {$this->key}'";
		} else if (($this->auth_mode == "jns_api_v2") && isset($this->key)) {
                        $H = trim($this->auth_header);
                        $headers .= " -H '{$H} {$this->key}'";
		}else if (($this->auth_mode == "jns_api_v2") && !isset($this->key)){
                        $auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
                }

		foreach($this->http_header_list as $header) {
			$H = trim($header);
			$headers .= " -H '{$H}'";
		}


		if(isset($this->fqdn))
		{
			$ip_address = $this->fqdn;
		}
		else
		{
			$ip_address = $this->sd_ip_config.":".$this->sd_management_port;
		}

		$curl_cmd = "curl " . $auth . " -X {$http_op} -sw '\nHTTP_CODE=%{http_code}' {$headers} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$this->protocol}://{$ip_address}{$rest_path}'";
		if (count($cmd_list) >2 ) {
			$rest_payload = $cmd_list[2];
			$curl_cmd .= " -d ";
			$curl_cmd .= "'{$rest_payload}'";
		}
		$curl_cmd .= " && echo";

		$this->execute_curl_command ( $origin, $rest_cmd, $curl_cmd  );
	}

	protected function execute_curl_command($origin, $rest_cmd, $curl_cmd) {
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
						throw new SmsException ( "$origin: Call to API {$rest_cmd} Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED );
					}
				}
			}
		}
		$xml;
		if (strpos($curl_cmd, "Content-Type: application/json")) {
			$result=preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result);
			$array = json_decode ( $result, true );
			if (isset ( $array ['sid'] )) {
				$this->key = $array ['sid'];
			}

			// call array to xml conversion function
			$xml = arrayToXml ( $array, '<root></root>' );
		} else {
			$xml = new SimpleXMLElement($result);
		}
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
	public $token_xpath = '//root/session';
	public $auth_header;
	public $key;
	
	public function do_connect() {

		$data = "";
		unset ( $this->key );
				
		$data = array (
				"user" => $this->sd_login_entry,
				"passwd" => $this->sd_passwd_entry
		);
		$params = array("data"=>$data, "url"=>"/sys/login/user");
		$params=array($params);
		$id = posix_getpid();
		$data=array("id"=>$id, "method"=>"exec", "params"=>$params);
		$data = json_encode ( $data );
		$cmd = "POST#{$this->sign_in_req_path}#{$data}";
		$result = $this->sendexpectone ( __FILE__ . ':' . __LINE__, $cmd );
		debug_dump($this->token_xpath, "do_connect result: \n");
		// extract token
		$this->key = (string)($result->xpath($this->token_xpath)[0]);
		debug_dump($this->key, "SESSION TOKEN\n");
	}
}

// return false if error, true if ok
function fortinet_fortimanager_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $model_data;

	$network = get_network_profile();
	$sd = &$network->SD;
	$class = "TokenConnection";
	$sms_sd_ctx = new $class ( $sd_ip_addr, $login, $passwd, "", $port_to_use );
	$sms_sd_ctx->token_xpath = "//root/session";
	$sms_sd_ctx->sign_in_req_path = "/jsonrpc";
	$sms_sd_ctx->auth_header ="";
	$sms_sd_ctx->protocol = "https";
	$sms_sd_ctx->auth_mode = "token";
	$sms_sd_ctx->conn_timeout = EXPECT_DELAY / 1000;
	$http_header_str ="Content-Type: application/json | Accept: application/json";
	$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	echo "rest_generic_connect: setting HTTP header to: ".print_r($sms_sd_ctx->http_header_list, true)."\n";
	if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
		$sms_sd_ctx->protocol=trim($sd->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE);
	}
	try
	{
		$sms_sd_ctx->do_connect();
	}
	catch (SmsException $e)
	{
		$sms_sd_ctx->disconnect();
		fortinet_fortimanager_disconnect();
		throw new SmsException($e->getMessage(), $e->getCode());
	}
	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function fortinet_fortimanager_disconnect() {
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
