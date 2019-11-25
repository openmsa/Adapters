<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class DeviceConnection extends GenericConnection {
	
	protected $key;
	protected $xml_response;
	protected $raw_xml;
	// values below can be customized in sms_router.conf
	public $content_type = "application/json";
	public $accept = "application/json";
	public $protocol = "https";
	
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
	
	function execute_curl_cmd($origin, $curl_cmd) {
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		
		$ret = exec_local ( $origin, $curl_cmd, $output_array );
		if ($ret !== SMS_OK) {
			throw new SmsException ( "Call to API Failed", $ret );
		}
		
		$result = '';
		foreach ( $output_array as $line ) {
			if ($line !== 'SMS_OK') {
				$result .= "{$line}\n";
			}
		}
		$this->xml_response = new SimpleXMLElement ( $result );
		$this->raw_xml = $this->xml_response->asXML ();
		//debug_dump ( $this->raw_xml, "DEVICE RESPONSE\n" );
	}
	
	public function sendCmd($origin, $cmd) {
		$this->send ( $origin, $cmd );
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
}

class GenericBASICConnection extends DeviceConnection {
	
	public function do_connect() {
	}
	
	public function send($origin, $cmd) {
		//echo "*** SEND cmd: {$cmd}\n";
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		$delay = EXPECT_DELAY / 1000;
		$cmd_list = preg_split('@#@', $cmd, 0, PREG_SPLIT_NO_EMPTY);
		$http_op = $cmd_list[0];
		$rest_path = "";
		if (count($cmd_list) >1 ) {
			$rest_path = $cmd_list[1];
		}
		
		$auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
		
		$curl_cmd = "curl " . $auth . " -X {$http_op} -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} -H 'Content-Type: {$this->content_type}' -H 'Accept: {$this->accept}' --max-time {$delay} -k '{$this->protocol}://{$this->sd_ip_config}:{$this->sd_management_port}{$rest_path}'";
		if (count($cmd_list) >2 ) {
			$rest_payload = $cmd_list[2];
			$curl_cmd .= " -d ";
			$curl_cmd .= "'{$rest_payload}'";
		}
		
		
		$curl_cmd .= " && echo";
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
						throw new SmsException ( "$origin: Call to API {$cmd} Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED );
					}
				}
			}
		}
		$xml;
		if (strpos($this->accept, "json")) {
			$array = json_decode ( $result, true );
			if (isset ( $array ['sid'] )) {
				
				//echo "\n!!!!!!!!!!!!!!KEY :" . $array ['sid'] . "!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
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
		//debug_dump ( $this->raw_xml, "DEVICE RESPONSE\n" );
	}
}

class GenericTokenConnection extends DeviceConnection {
	
	public function do_connect() {
		unset ( $this->key );
		$data = array (
				'user' => $this->sd_login_entry,
				'password' => $this->sd_passwd_entry 
		);
		
		$data = json_encode ( $data );
		
		$cmd = "login' -d '" . $data;
		$result = $this->sendexpectone ( __FILE__ . ':' . __LINE__, $cmd );
	}
	public function send($origin, $cmd) {
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		$delay = EXPECT_DELAY / 1000;
		
		$header = "";
		
		if (isset ( $this->key )) {
			$header = "-H 'X-chkp-sid: " . $this->key . "'";
		}		
		
		$curl_cmd = "curl -XPOST -sw '\nHTTP_CODE=%{http_code}' --connect-timeout {$delay} -H 'Content-Type: application/json' {$header} --max-time {$delay} -k 'https://{$this->sd_ip_config}:{$this->sd_management_port}/web_api/{$cmd}";
		
		$curl_cmd .= "' && echo";
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
						throw new SmsException ( "$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED );
					}
				}
			}
		}
		
		$array = json_decode ( $result, true );
		if (isset ( $array ['sid'] )) {
			
			//echo "\n!!!!!!!!!!!!!!KEY :" . $array ['sid'] . "!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
			$this->key = $array ['sid'];
		}
		
		// call array to xml conversion function
		$xml = arrayToXml ( $array, '<root></root>' );
		
		$this->xml_response = $xml; // new SimpleXMLElement($result);
		$this->raw_json = $result;
		
		// FIN AJOUT
		$this->raw_xml = $this->xml_response->asXML ();
		debug_dump ( $this->raw_xml, "DEVICE RESPONSE\n" );
	}
}

// return false if error, true if ok
function rest_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $model_data;
	
	$data = json_decode ( $model_data, true );
	
	if (isset($data ['class'])) {
		$class = $data ['class'];	
		echo "rest_generic_connect: using connection class: " . $class . "\n";
		$sms_sd_ctx = new $class ( $sd_ip_addr, $login, $passwd, $port_to_use );
	} else { 
		$sms_sd_ctx = new GenericBASICConnection($sd_ip_addr, $login, $passwd, $port_to_use);
	}
	if (isset($data ['header-content-type'])) {
		$sms_sd_ctx->content_type=$data ['header-content-type'];
	} else {
		$sms_sd_ctx->content_type="application/json";
	}
	if (isset($data ['header-accept'])) {
		$sms_sd_ctx->content_type=$data ['header-accept'];
	} else {
		$sms_sd_ctx->content_type="application/json";
	}
	if (isset($data ['protocol'])) {
		$sms_sd_ctx->protocol=$data ['protocol'];
	} else {
		$sms_sd_ctx->protocol="https";
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

?>
