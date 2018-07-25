<?php
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'cmd_create.php');
require_once load_once('smsd', 'cmd_read.php');
require_once load_once('smsd', 'cmd_update.php');
require_once load_once('smsd', 'cmd_delete.php');
require_once load_once('smsd', 'cmd_import.php');
require_once load_once('smsd', 'cmd_list.php');

require_once "$db_objects";

class param {
	var $object_id;
}

class openflow_command
{
	private $parser_list;
	private $parsed_objects;
	private $create_list;
	private $delete_list;
	private $list_list;
	private $read_list;
	private $update_list;
	private $configuration;
	private $restcall;
	private $flows_names;

	//const to put in configurator in next step?
	const ADDR_CONTROLEUR_OPENFLOW = '127.0.0.1:63080';
	const REST_SERVICE = '/wm/staticflowentrypusher/json';
	private $url_rest;


	function __construct()
	{
    parent::__construct();
	  $this->parser_list = array();
		$this->create_list = array();
		$this->delete_list = array();
		$this->list_list = array();
		$this->read_list = array();
		$this->update_list = array();
		$this->restcall = array();
		$this->flows_names = array();
		$this->url_rest = "http://".self::ADDR_CONTROLEUR_OPENFLOW.self::REST_SERVICE;
	}

	/*
	 * #####################################################################################
	* CREATE
	* #####################################################################################
	*/

	/**
	 * Decode XML definition of CREATE command
	 * @param string $object object_id
	 * @param string $json_params JSON formatted parameters for this object
	 * @param DomElement $element command defintion
	 */
	function decode_CREATE($object, $json_params, $element)
	{
		$this->create_list[] = new cmd_create($object, $element, $json_params);
	}

	function eval_CREATE()
	{
		foreach ($this->create_list as $create)
		{
			$conf = $create->evaluate();
			$this->configuration .= $conf;
			//$SMS_RETURN_BUF .= $conf;
		}


		$delay = 50;

		$line = get_one_line($this->configuration);
		$dpid = get_network_profile()->SD->SD_DPID;
		$line_count = 0;
		/*Each line in template is a FLOW and has to be completed with information to be send to FLOODLIGHT REST API :
		 - the switch dpid
		 - a flow name that needs to be uniq. An object is one or more flow, so for an object, an uniqid is generated and flow ids = uniqid-0, uniqid-1, uniqid-2 ..
		 - the openflow controller REST address : http://127.0.0.1:63080/wm/staticflowentrypusher/json
		 Once completed, the rest CALL is stored in $this->restcall[] and flow names in $this->flows_names[] for later use
		 Example:
		 curl -d '{"switch": "12:21:00:00:00:00:00:01", "name":"5149c2233a73f-0","ingress-port":"1","actions":"output=5"}' http://ofcontroller:63080/wm/staticflowentrypusher/json
		*/
		$flowname = uniqid()."-";
		while ($line !== false)
		{
			if(strlen(trim($line)) > 0){
				debug_dump($line, "CONTENT LINE");
				$command_curl = "curl --connect-timeout {$delay} --max-time {$delay} -d ";
				$json_for_command = "'{\"switch\": \"".$dpid."\", \"name\":\"".$flowname.$line_count."\",".$line."}' ";
				$curl = $command_curl.$json_for_command.$this->url_rest;
				$this->restcall[] = $curl;
				$SMS_RETURN_BUF .= $curl."\n";
				$this->flows_names[] = $flowname.$line_count;
				$line_count++;
			}
			$line = get_one_line($this->configuration);
		}
		debug_dump($this->restcall, "REST call for create");



		return SMS_OK;
	}

	/**
	 * Apply created object to device and if OK add object to the database.
	 */
	function apply_device_CREATE($params)
	{

		//debug_dump($this->restcall, "FLOW TO PUSH TO THE CONTROLLER");

		//$ret = sd_apply_conf($this->configuration, true);


		//Exec all REST call stored in $this->restcall
		foreach ($this->restcall as $call)
		{
			$result=exec($call,$array_result,$code);

			if($code != 0){
				//$ret = "Connection problem to OpenFlow Controller";
				$ret = ERR_SD_NETWORK;
			}else{
				$retourstrpos=strpos($result,"Entry pushed");
				if($retourstrpos === false){
					//flow error
					$ret = ERR_SD_NETWORK;
				}
				else
				{
					$ret = SMS_OK;
				}
			}

		}



		return $ret;
	}


	/**
	 * Apply created object to device and if OK add object to the database.
	 */
	function apply_base_CREATE($params)
	{
		global $sms_csp;
		global $sms_sd_info;

		$ret = SMS_OK;

		debug_dump($params, "PARAMS");
		debug_dump($this->flows_names, "FLOWLIST");

		//add $this->flows_names[] to the params before to persist it

		$tabkey = array_keys($params);
		$firstKey = $tabkey[0];
		$tabkeyDeep = array_keys($params[$firstKey]);
		$secondKey = $tabkeyDeep[0];
		debug_dump($firstKey, "firstKey");
		debug_dump($secondKey, "secondKey");
		$params[$firstKey][$secondKey]["flowsnames"]=$this->flows_names;

		debug_dump($params, "PARAMS AND FLOWLIST");

		return set_conf_object_to_db($params);
	}

	/*
	 * #####################################################################################
	* UPDATE
	* #####################################################################################
	*/

	function decode_UPDATE($object, $json_params, $element)
	{
		$this->create_list[] = new cmd_update($element, $json_params);
	}

	function eval_UPDATE()
	{
		foreach ($this->create_list as $create)
		{
			$conf = $create->evaluate();
			$this->configuration .= $conf;
			//$SMS_RETURN_BUF .= $conf;
		}

	  $delay = 50;

		/* For update we Delete every flow for objectid passed then Create flow resolved in update section
		Example:
		curl -X DELETE -d '{"name":"flow-mod-1"}' http://<controller_ip>:8080/wm/staticflowentrypusher/json
		curl -d '{"switch": "12:21:00:00:00:00:00:01", "name":"flow-mod-1","ingress-port":"1","actions":"output=5"}' http://ofcontroller:63080/wm/staticflowentrypusher/json
		*/


		//loop on every flowsnames from database for this object_id
		//foreach build a DELETE rest call  and stock it into $this->restcall[]
		$crud_list = get_network_profile()->SD->SD_CRUD_OBJECT_list;

		foreach($crud_list as $name => $value){
			if (strpos($name, 'flowsnames') !== false){
			  $command_name = "curl --connect-timeout {$delay} --max-time {$delay} -X DELETE -d ";
				$json_for_command = "'{\"name\": \"".$value."\"}' ";
				$curl = $command_name.$json_for_command.$this->url_rest;
				$this->restcall[] = $curl;
				$SMS_RETURN_BUF .= $curl."\n";
			}
		}

		//build CREATE rest call(s)
		$dpid = get_network_profile()->SD->SD_DPID;
		$line_count = 0;
		$flowname = uniqid()."-";
		$line = get_one_line($this->configuration);
		while ($line !== false)
		{
			if(strlen(trim($line)) > 0){
				debug_dump($line, "CONTENT LINE");
				$command_name = "curl --connect-timeout {$delay} --max-time {$delay} -d ";
				$json_for_command = "'{\"switch\": \"".$dpid."\", \"name\":\"".$flowname.$line_count."\",".$line."}' ";
				$curl = $command_name.$json_for_command.$this->url_rest;
				$this->restcall[] = $curl;
				$SMS_RETURN_BUF .= $curl."\n";
				$this->flows_names[] = $flowname.$line_count;
				$line_count++;
			}
			$line = get_one_line($this->configuration);
		}
		debug_dump($this->restcall, "REST call for update");



		return SMS_OK;
	}


	function apply_device_UPDATE($params)
	{

		//Exec all REST call stored in $this->restcall
		foreach ($this->restcall as $call)
		{
			$result=exec($call,$array_result,$code);

			if($code != 0){
				//$ret = "Connection problem to OpenFlow Controller";
				$ret = ERR_SD_NETWORK;
			}else{
				$retourstrpos=strpos($result,"Entry pushed");
				if($retourstrpos === false){
					//flow error
					$ret = ERR_SD_NETWORK;
				}
				else
				{
					$ret = SMS_OK;
				}
			}

		}

		return $ret;
	}


	function apply_base_UPDATE($params)
	{
		global $sms_csp;
		global $sms_sd_info;

		$ret = SMS_OK;

		debug_dump($params, "PARAMS");

		//DELETE
		object_conf_to_var($params, $vars);
		debug_dump($vars, "OBJECTS TO DELETE IN DATABASE");

		$tabkey = array_keys($vars);
		$firstkey = $tabkey[0];

		$vars_only_name_id = array($firstkey => $vars[$firstkey]);
		if (!empty($vars))
		{
			foreach ($$vars_only_name_id as $name => $value)
			{
				$to_del = "{$name}.$value";
				$ret = sms_bd_delete_conf_objects($sms_csp, $sms_sd_info, $to_del);
				if ($ret !== SMS_OK)
				{
					return $ret;
				}
			}
		}




		debug_dump($this->flows_names, "FLOWLIST");

		//CREATE
		//add $this->flows_names[] to the params before to persist it

		$tabkey = array_keys($params);
		$firstKey = $tabkey[0];
		$tabkeyDeep = array_keys($params[$firstKey]);
		$secondKey = $tabkeyDeep[0];
		debug_dump($firstKey, "firstKey");
		debug_dump($secondKey, "secondKey");
		$params[$firstKey][$secondKey]["flowsnames"]=$this->flows_names;

		debug_dump($params, "PARAMS AND FLOWLIST");

		return set_conf_object_to_db($params);
	}



	/*
	 * #####################################################################################
	* DELETE
	* #####################################################################################
	*/

	/**
	 * Decode XML definition of DELETE command
	 * @param string $object object_id
	 * @param string $json_params JSON formatted parameters for this object
	 * @param DomElement $element command defintion
	 */
	function decode_DELETE($object, $json_params, $element)
	{
		$this->delete_list[] = new cmd_delete($element, $json_params);
	}

	function eval_DELETE()
	{
		foreach ($this->delete_list as $delete)
		{
			$conf = $delete->evaluate();
			$this->configuration .= $conf;
			//$SMS_RETURN_BUF .= $conf;
		}

		//curl -X DELETE -d '{"name":"flow-mod-1"}' http://<controller_ip>:8080/wm/staticflowentrypusher/json
   	$delay = 50;
		$line = get_one_line($this->configuration);
		$dpid = get_network_profile()->SD->SD_DPID;
		while ($line !== false)
		{
			if(strlen(trim($line)) > 0){
				debug_dump($line, "CONTENT LINE");
				$command_name = "curl --connect-timeout {$delay} --max-time {$delay} -X DELETE -d ";
				$json_for_command = "'{\"name\": \"".trim($line)."\"}' ";
				$curl = $command_name.$json_for_command.$this->url_rest;
				$this->restcall[] = $curl;
				$SMS_RETURN_BUF .= $curl."\n";
			}
			$line = get_one_line($this->configuration);
		}

		debug_dump($this->restcall, "REST call for delete");


		return SMS_OK;
	}

	/**
	 * Apply deleted object to device and if OK add object to the database.
	 */
	function apply_device_DELETE($params)
	{

		//debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

		//$ret = sd_apply_conf($this->configuration, true);


		//Exec all REST call stored in $this->restcall
		//{"status" : "Entry 5149b76a3fc92-1 deleted"}
		foreach ($this->restcall as $call)
		{
			$result=exec($call,$array_result,$code);

			if($code != 0){
				//$ret = "Connection problem to OpenFlow Controller";
				$ret = ERR_SD_NETWORK;
			}else{
				$retourstrpos=strpos($result,"deleted");
				if($retourstrpos === false){
					//flow error
					$ret = ERR_SD_NETWORK;
				}
				else
				{
					$ret = SMS_OK;
				}
			}

		}

		return $ret;
	}

	/**
	 * Apply deleted object to device and if OK add object to the database.
	 */
	function apply_base_DELETE($params)
	{
		global $sms_csp;
		global $sms_sd_info;
		$ret = SMS_OK;

		object_conf_to_var($params, $vars);
		debug_dump($vars, "OBJECTS TO DELETE IN DATABASE");
		if (!empty($vars))
		{
			foreach ($vars as $name => $value)
			{
				$to_del = "{$name}.$value";
				$ret = sms_bd_delete_conf_objects($sms_csp, $sms_sd_info, $to_del);
				if ($ret !== SMS_OK)
				{
					return $ret;
				}
			}
		}

		return $ret;
	}




}


?>