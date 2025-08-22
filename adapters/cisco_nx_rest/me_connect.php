<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";
require_once 'smsd/ssh_connection.php';
require_once load_once ('cisco_nx_rest', 'common.php');

class DeviceConnection extends GenericConnection {
  
	protected $xml_response;
	protected $raw_xml;
	public $http_header_list;
	public $protocol;
	public $conn_timeout;

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
		$this->sdid = $SD->SDID;
		
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

	public function get_raw_json() {
    		return $this->raw_json;
    	}

	public function send($origin, $rest_cmd) {
		unset ( $this->xml_response );
		unset ( $this->raw_xml );
		echo ("send(): rest_cmd = ".$rest_cmd."\n");
		$cmd_list = preg_split('@#@', $rest_cmd, 0, PREG_SPLIT_NO_EMPTY);
		debug_dump ( $cmd_list, "CMD_LIST\n" );

		$http_op = $cmd_list[0];
		$rest_path = "";
		if (count($cmd_list) >1 ) {
			$rest_path = $cmd_list[1];
		}

		$headers = "";
		$auth = " -u " . $this->sd_login_entry . ":" . $this->sd_passwd_entry;
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
			
			$payload_file=tempnam("/opt/sms/spool/tmp/","payload_".$this->sdid."_");
			file_put_contents($payload_file, $rest_payload);
			$curl_cmd .= ' @'.$payload_file;
	
		}
		$curl_cmd .= " && echo";

		$this->execute_curl_command ( $origin, $rest_cmd, $curl_cmd, $payload_file );
	}

	protected function execute_curl_command($origin, $rest_cmd, $curl_cmd, $payload_file="" ) {
		
		

		$ret = exec_local ( $origin, $curl_cmd, $output_array );
		
		if ( $payload_file !== '' && file_exists($payload_file) )
                {
                       unlink($payload_file);
                }
		
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
		$result = preg_replace('/xmlns="[^"]+"/', '', $result);
		//test if result is a json content or not
		json_decode(preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result));
		if (json_last_error() === JSON_ERROR_NONE )) {
            $result=preg_replace('/":([0-9]+)\.([0-9]+)/', '":"$1.$2"', $result);
			$array = json_decode ( $result, true );
			if (isset ( $array ['sid'] )) {
				$this->key = $array ['sid'];
			}
			// call array to xml conversion function
			$xml = arrayToXml ( $array, '<root></root>' );
		} else {
            if (empty(trim($result))) {
		        $result="<root></root>";
		    }
		    $result = str_replace('localProxyArNoHwFlood', 'localProxyArpNoHwFlood', $result);
            $result = str_replace('<eerstats-items', '<peerstats-items', $result);
            $result = str_replace('localroxyArpNoHwFlood', 'localProxyArpNoHwFlood', $result);
            $result = str_replace('peerTye', 'peerType', $result);
            $result = str_replace('autoCoy', 'autoCopy', $result);
            $result = str_replace('su1', 'sup1', $result);
			$xml = new SimpleXMLElement($result);
		}
		$this->xml_response = $xml; // new SimpleXMLElement($result);
		$this->raw_json = $result;

		$this->raw_xml = $this->xml_response->asXML ();
		//debug_dump ( $this->raw_xml, "DEVICE RESPONSE\n" );
	}

}

class GenericBASICConnection extends DeviceConnection {

	public function do_connect() {
	}

}

// return false if error, true if ok
function me_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
	global $sms_sd_ctx;
	global $model_data;

	//$data = json_decode (trim($model_data), true );

	$network = get_network_profile();
	$sd = &$network->SD;
	//debug_dump($sd, "SD\n");

	//debug_dump($sd->SD_CONFIGVAR_list, "SD_CONFIGVAR_list\n");

	$class = "GenericBASICConnection";

	echo "me_connect: using connection class: " . $class . "\n";
	$sms_sd_ctx = new $class ( $sd_ip_addr, $login, $passwd, "", $port_to_use );

	$http_header_str ="Content-Type: application/json | Accept: application/json";
	if (isset($sd->SD_CONFIGVAR_list['HTTP_HEADER'])) {
		$http_header_str = $sd->SD_CONFIGVAR_list['HTTP_HEADER']->VAR_VALUE;
		$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	}
	$sms_sd_ctx->http_header_list = explode("|", $http_header_str);
	echo "me_connect: setting HTTP header to: ".print_r($sms_sd_ctx->http_header_list, true)."\n";

	$sms_sd_ctx->protocol = "https";
	if (isset($sd->SD_CONFIGVAR_list['PROTOCOL'])) {
		$sms_sd_ctx->protocol=trim($sd->SD_CONFIGVAR_list['PROTOCOL']->VAR_VALUE);
	}
	echo  "me_connect: setting HTTP protocol to: {$sms_sd_ctx->protocol}\n";

	$sms_sd_ctx->conn_timeout = EXPECT_DELAY / 1000;
	if (isset($sd->SD_CONFIGVAR_list['CONN_TIMEOUT'])) {
		$sms_sd_ctx->conn_timeout=trim($sd->SD_CONFIGVAR_list['CONN_TIMEOUT']->VAR_VALUE);
	}
	echo  "me_connect: setting HTTP timeout to: {$sms_sd_ctx->conn_timeout}\n";

	
	if (isset($sd->SD_CONFIGVAR_list['NO_SAVE_CONFIG_TO_START_UP_ON_APPLY_CONF'])) {
		$sms_sd_ctx->no_save_config_to_startup = trim($sd->SD_CONFIGVAR_list['NO_SAVE_CONFIG_TO_START_UP_ON_APPLY_CONF']->VAR_VALUE);
	}

	try
	{
		$sms_sd_ctx->do_connect();
	}
	catch (SmsException $e)
	{
		$sms_sd_ctx->disconnect();
		me_disconnect();
		throw new SmsException($e->getMessage(), $e->getCode());
	}

	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function me_disconnect() {
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}

// return false if error, true if ok
function me_cli_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
  global $sms_sd_ctx;
  $port_to_use="22";
  if (isset($sd->SD_CONFIGVAR_list['SSH_PORT'])) {
     $port_to_use = $sd->SD_CONFIGVAR_list['SSH_PORT']->VAR_VALUE;
  }
	try{
		$sms_sd_ctx = new CiscoNXsshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
		$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		return ERR_SD_CONNREFUSED;
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function me_cli_disconnect()
{
  global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
  	$tab[0] = ')#';
  	$tab[1] = $sms_sd_ctx->getPrompt();

  	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
  	while($index == 0)
  	{
  	  sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'end', $tab);
  	}
  	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

class CiscoNXsshConnection extends SshConnection
{

	public function do_post_connect()
	{
		echo "***Call cisco NX Do_post_connect***\n";
		unset($tab);
		$tab[0] = '#';
		$tab[1] = '$';
		$tab[2] = '>';
		$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

		if($result_id === 2) {
			$this->sendCmd(__FILE__.':'.__LINE__, "en ");
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_admin_passwd_entry}");
			$result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
		}

		if($result_id !== 0) {
			throw new SmsException("Connection Failed, can't enter in Enable mode", ERR_SD_CONNREFUSED);
		}
		# to get large output without More prompt
		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal length 0",'#');
		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal width 0",'#');
		$this->sendexpectone(__FILE__.':'.__LINE__, "terminal exec prompt no-timestamp",'#');
    
	}

	public function do_store_prompt() {
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'conf t', '(config)#');
		$buffer = sendexpectone(__FILE__.':'.__LINE__, $this, 'exit', '#');
		$this->prompt= trim($buffer);
		$this->prompt = substr(strrchr($buffer, "\n"), 1);

		echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
	}
}
?>
