<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class MeConnection extends GenericConnection {

	protected $xml_response;
	protected $array_response;
	public $http_header_list = array(
	    'GET' => array('Accept: application/json'),
	    'POST' => array('Content-Type: application/json', 'Accept: application/json'),
	    'DELETE' => array('Accept: application/json'),
	);
	public $http_header_custom = null;
	public $protocol = 'https';
	public $auth_header = 'X-auth-access-token:';
	public $conn_timeout = EXPECT_DELAY / 1000;
	#public $sign_in_req_path = '/api/fdm/latest/fdm/token';
	public $sign_in_req_path = '/api/fmc_platform/v1/auth/generatetoken';
	public $access_token = 'X-auth-access-token';
	public $key;

	public function do_connect() {

	  $data = "";
	  if (!isset($this->key)) 
	  {
 		  $cmd = "POST#{$this->sign_in_req_path}#{$data}";
		  $this->send ( __FILE__ . ':' . __LINE__, $cmd , "-i ");
	  }
	}

	public function do_disconnect() {
	  /*
	  if (isset($this->key)) {
	    // revoke token
	    $data = array (
	        "grant_type" => "revoke_token",
	        "X-auth-access-token:" => $this->key,
	        "token_to_revoke" => $this->key
	    );
	    $data = json_encode ( $data );
	    $cmd = "POST#{$this->sign_in_req_path}#{$data}";
	    $this->send ( __FILE__ . ':' . __LINE__, $cmd );
	  }
	  */
	}

	public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true) {

        $this->send ( $origin, $cmd );

		if ($prompt !== 'lire dans sdctx' && ! empty ( $prompt )) {
			$tab [0] = $prompt;
		} else {
			$tab = array ();
		}

		$this->expect ( $origin, $tab );

		if (is_array ( $this->last_result )) {
		  return $this->last_result[0];
		}
		return $this->last_result;
	}


	public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result') {
		global $$global_result_name;

		if (! isset ( $this->xml_response )) {
			throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
		}
		$index = 0;
		if (empty ( $tab )) {
			$$global_result_name = $this->xml_response;
			$this->last_result = $this->xml_response;
			return $index;
		}
		foreach ( $tab as $path ) {
			$result = $this->xml_response->xpath ( $path );
			if (($result !== false) && ! empty ( $result )) {
				$$global_result_name = $result;
				$this->last_result = $result;
				return $index;
			}
			$index ++;
		}

		throw new SmsException("cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT, $origin);
	}

	public function send($origin, $rest_cmd, $additional_params = null) {
		unset ( $this->xml_response );
		$cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);

		$http_op = $cmd_list[0];
		$rest_path = '';
		if (count($cmd_list) > 1 ) {
			$rest_path = $cmd_list[1];
		}

		$headers = '';
		$auth = '';

		if (isset($this->key)) {
            $headers = "-H '{$this->auth_header} {$this->key}'";
		} else {
		    $auth = "-u {$this->sd_login_entry}:{$this->sd_passwd_entry}";
		}

		if (isset($this->http_header_custom)) {
		  $headers .= $this->http_header_custom;
		} else if (!empty($this->http_header_list[$http_op])) {
    		foreach($this->http_header_list[$http_op] as $header) {
    			$headers .= " -H '{$header}'";
    		}
		}

		$ip_address = $this->sd_ip_config.":".$this->sd_management_port;

		$curl_cmd = "curl {$auth} -X {$http_op} -sw '\nHTTP_CODE=%{http_code}' {$headers} --connect-timeout {$this->conn_timeout} --max-time {$this->conn_timeout} -k '{$this->protocol}://{$ip_address}{$rest_path}'";
		if (count($cmd_list) > 2 ) {
			$rest_payload = $cmd_list[2];
			$curl_cmd .= " -d '{$rest_payload}'";
		}

		if (!empty($additional_params))
		{
		  $curl_cmd .= " {$additional_params}";
		}
		$curl_cmd .= ' && echo';

		$this->execute_curl_command ( $origin, $rest_cmd, $curl_cmd );
	}

	protected function execute_curl_command($origin, $rest_cmd, $curl_cmd) {
		$ret = exec_local ( $origin, $curl_cmd, $output_array );
		if ($ret !== SMS_OK) {
		    $out_str = implode("\n", $output_array);
		    $msg = "Command $curl_cmd Failed, $out_str";
		    throw new SmsException($msg, $ret, $origin);
		}

		$result = '';
		foreach ( $output_array as $line ) {
			if ($line !== 'SMS_OK') {
				if (strpos ( $line, 'HTTP_CODE' ) === false) {
					$result .= "{$line}\n";
				} else {
					if (strpos ( $line, 'HTTP_CODE=20' ) === false) {
						$cmd_quote = str_replace ( "\"", "'", $result );
						$cmd_return = str_replace ( "\n", "", $cmd_quote );
						throw new SmsException("Call to API {$rest_cmd} Failed = $line, $cmd_return error", ERR_SD_CMDFAILED, $origin);
					}
				}
			}
		}

		if (!empty($result))
        {
    		$result=preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result);
    		$array = json_decode ( $result, true );
    		if (isset ( $array ['sid'] )) {
    			$this->key = $array ['sid'];
    		}elseif (preg_match('/X-auth-access-token: ([^\\\]*?)\\n/', $result, $match) == 1){
			$this->key = $match[1];	
			debug_dump( $this->key, "X-auth-access-token:");
		}
        }
        else
        {
            $array = null;
        }

		$this->array_response = $array;
		// call array to xml conversion function
		$this->xml_response = arrayToXml ( $array );

		debug_dump ( $this->xml_response->asXML (), "DEVICE RESPONSE\n" );
	}

	public function get_array_response() {
	  return $this->array_response;
	}

	public function set_custom_headers($http_headers) {
	  $this->http_header_custom = $http_headers;
	}

	public function reset_custom_headers() {
	  $this->http_header_custom = null;
	}
}

// return false if error, true if ok
function me_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $SMS_OUTPUT_BUF;

	try
	{
        $sms_sd_ctx = new MeConnection( $sd_ip_addr, $login, $passwd, null, $port_to_use );
	}
	catch (SmsException $e)
	{
		me_disconnect();
		$SMS_OUTPUT_BUF = $e->getMessage();
		return $e->getCode();
	}

	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function me_disconnect() {
	global $sms_sd_ctx;

	if (isset($sms_sd_ctx))
	{
    	$sms_sd_ctx->disconnect();
    	$sms_sd_ctx = null;
	}
	return SMS_OK;
}

/*
 * This function must be called with an active connection
 * It closes the connection and re-opens a new one
 */
function wait_until_me_is_up()
{
  global $SMS_OUTPUT_BUF;

  me_disconnect();

  $waiting_time = 1; // seconds
  $timeout = 120; // seconds
  do
  {
    sleep($waiting_time);
    $timeout -= $waiting_time;

    $ret = me_connect();

    $state = ($ret == SMS_OK) ? 'END' : 'IN_PROGRESS';
  }
  while ($state == 'IN_PROGRESS' && $timeout > 0);

  if ($state != 'END')
  {
    $msg = "Config import failed, reconnect to the ME timeout, $SMS_OUTPUT_BUF";
    throw new SmsException($msg, $ret, __FILE__ . ':' . __LINE__);
  }
}

?>
