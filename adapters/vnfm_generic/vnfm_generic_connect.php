<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once "$db_objects";

class Nfvo_connection extends GenericConnection
{

	private $endPointsURL;

	private $xml_response;

	private $raw_xml;

	private $raw_json;

	// ------------------------------------------------------------------------------------------------
	public function do_connect()
	{
		$this->endPointsURL = $endPointsURL_table[0];
	}

	// ------------------------------------------------------------------------------------------------
	public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
	{
		global $sendexpect_result;
		$this->send($origin, $cmd);

		if ($prompt !== 'lire dans sdctx' && ! empty($prompt)) {
			$tab[0] = $prompt;
		} else {
			$tab = array();
		}

		$this->expect($origin, $tab);

		if (is_array($sendexpect_result)) {
			return $sendexpect_result[0];
		}
		return $sendexpect_result;
	}

	// ------------------------------------------------------------------------------------------------
	public function send($origin, $cmd)
	{
		unset($this->xml_response);
		unset($this->raw_xml);

		$network = get_network_profile();
		$sd = &$network->SD;

		$delay = EXPECT_DELAY / 1000;
		// MODIF LO

		// MODIF LO : la commande $cmd est décomposée en quatre parties pour Openstack : GET#:endpoint (nova,keystone...)#/tenants...#parametres creation
		$action = explode("#", $cmd);

		// SI pas de endpoints, on prend keystone par défaut.
		// if ($action[1] == "")
		// {
		$action[2] = 'http://' . $this->sd_ip_config . ':8380' . $action[2];
		// }

		// TODO TEST validité champ ACTION[]
		$curl_cmd = "curl --tlsv1.2 -i -sw '\nHTTP_CODE=%{http_code}' -u {$this->sd_login_entry}:{$this->sd_passwd_entry} --connect-timeout {$delay} --max-time {$delay} -X {$action[0]} -H \"Version: 2.6.1\" -H \"Content-Type: application/json\" -k '{$action[2]}'";
		if (isset($action[3])) {
			$curl_cmd .= " -d '{$action[3]}'";
		}

		echo "{$cmd} for endPoint {$action[1]}\n";

		$curl_cmd .= " && echo";
		$ret = exec_local($origin, $curl_cmd, $output_array);

		if ($ret !== SMS_OK) {
			throw new SmsException("Call to API Failed", $ret);
		}

		$result = '';
		foreach ($output_array as $line) {
			if ($line !== 'SMS_OK') {
				if (strpos($line, 'HTTP_CODE') !== 0) {
					$result .= "{$line}\n";
				} else {
					if (strpos($line, 'HTTP_CODE=20') !== 0) {
						$cmd_quote = str_replace("\"", "'", $result);
						$cmd_return = str_replace("\n", "", $cmd_quote);
						throw new SmsException("$origin: Call to API Failed = $line, $cmd_quote error", ERR_SD_CMDFAILED);
					}
				}
			}
		}

		echo "%%%%%%%%%%%%%%%%%%%%% RESULT = {$result} %%%%%%%%%%%%%%%%%%%%%%%\n";
		$result = rtrim($result);
		$headers_and_response = explode("\n\n", $result);
		$headers_and_response_count = count($headers_and_response);
		if ($headers_and_response_count > 1) {
			$raw_headers = $headers_and_response[$headers_and_response_count - 2];
			$response_body = $headers_and_response[$headers_and_response_count - 1];
			$response_headers = http_parse_headers($raw_headers);
			if (array_key_exists('X-Subject-Token', $response_headers)) {
				$this->key = $response_headers['X-Subject-Token'];
			}
		} else {
			$response_body = "";
		}

		$array = json_decode($response_body, true);

		// call array to xml conversion function
		$xml = arrayToXml($array, '<root></root>');

		$this->xml_response = $xml; // new SimpleXMLElement($result);
		$this->raw_json = $response_body;

		// FIN AJOUT
		$this->raw_xml = $this->xml_response->asXML();
		debug_dump($this->raw_xml, "DEVICE RESPONSE\n");
	}

	// ------------------------------------------------------------------------------------------------
	public function sendCmd($origin, $cmd)
	{
		$this->send($origin, $cmd);
	}

	// ------------------------------------------------------------------------------------------------
	public function expect($origin, $tab, $delay = EXPECT_DELAY, $display_error = true, $global_result_name = 'sendexpect_result')
	{
		global $$global_result_name;

		if (! isset($this->xml_response)) {
			throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
		}
		$index = 0;
		if (empty($tab)) {
			$result = $this->xml_response;
			$$global_result_name = $result;
			return $index;
		}
		foreach ($tab as $path) {

			$result = $this->xml_response->xpath($path);
			if (($result !== false) && ! empty($result)) {
				$$global_result_name = $result;
				return $index;
			}
			$index ++;
		}

		throw new SmsException("$origin: cmd timeout, $tab[0] not found", ERR_SD_CMDTMOUT);
	}

	public function get_raw_xml()
	{
		return $this->raw_xml;
	}

	public function get_raw_json()
	{
		return $this->raw_json;
	}
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function vnfm_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
	global $sms_sd_ctx;

	$sms_sd_ctx = new Nfvo_connection($sd_ip_addr, $login, $passwd, $port_to_use);
	return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function vnfm_generic_disconnect()
{
	global $sms_sd_ctx;
	$sms_sd_ctx = null;
	return SMS_OK;
}

