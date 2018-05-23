<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";

class FortinetGenericsshConnection extends SshConnection
{
  public function do_store_prompt()
  {
  	$network = get_network_profile();

  	$IS_VDOM_ENABLED=false;

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if ((strpos($buffer, 'License Status') === false) || (strpos($buffer, 'License Status: Valid') !== false) || (strpos($buffer, 'License Status: Warning') !== false))
    {
    	if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
			//If VDOM is enabled for generic commands do config global
	    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
	    	$IS_VDOM_ENABLED=true;
    	}
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config system console', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'set output standard', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
      if ($IS_VDOM_ENABLED){
      	//If the device is a VDOM come out of global mode and enter vdom mode
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
		    $cmd = "edit root";
      	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
    	$buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, '', '#',40000);
    	$this->prompt = trim($buffer);
    	$this->prompt = substr(strrchr($buffer, "\n"), 1);
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }
  public function do_start()
  {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
}

class FortinetVDOMsshConnection extends SshConnection
{
  public function do_store_prompt()
  {
    $network = get_network_profile();
    $SD = &$network->SD;
    $dev_name=$SD->SD_HOSTNAME;

    $IS_VDOM_ENABLED=false;

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if ((strpos($buffer, 'License Status') === false) || (strpos($buffer, 'License Status: Valid') !== false) || (strpos($buffer, 'License Status: Warning') !== false))
    {
      if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
        //If VDOM is enabled for generic commands do config global
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
        $IS_VDOM_ENABLED=true;
      }
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config system console', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'set output standard', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
      if ($IS_VDOM_ENABLED){
        //If the device is a VDOM come out of global mode and enter vdom mode
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
        $cmd = "edit $dev_name";
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
      $this->prompt = " # ";
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }
  public function do_start()
  {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
}

class FortinetsshKeyConnection extends SshKeyConnection
{

  public function do_store_prompt()
  {
    $network = get_network_profile();

    $IS_VDOM_ENABLED=false;

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'get system status', '#');
    if (strpos($buffer, 'License Status') == false || strpos($buffer, 'License Status: Valid') !== false || strpos($buffer, 'License Status: Warning') !== false)
    {
      if(strpos($buffer, 'Virtual domain configuration: enable') !== false){
        //If VDOM is enabled for generic commands do config global
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config global', '(global) #', 40000);
        $IS_VDOM_ENABLED=true;
      }
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config system console', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'set output standard', '(console) #', 40000);
      $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
      if ($IS_VDOM_ENABLED){
        //If the device is a VDOM come out of global mode and enter vdom mode
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'end', '#');
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, 'config vdom', '(vdom) #', 40000);
        $cmd = "edit root";
        $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $this, $cmd, '#', 40000);
      }
      $this->prompt = trim($buffer);
      $this->prompt = substr(strrchr($buffer, "\n"), 1);
    }
    else
    {
      $this->prompt = " # ";
    }

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }
  public function do_start()
  {
    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function fortinet_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;
  global $model_data;
  global $priv_key;

  $data = json_decode($model_data, true);
  $class = $data['class'];
  if (isset($data['priv_key']))
  {
    $priv_key = $data['priv_key'];
  }

  $sms_sd_ctx = new $class($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function fortinet_generic_disconnect()
{
  global $sms_sd_ctx;
  if (!is_null($sms_sd_ctx) && method_exists($sms_sd_ctx, 'sendCmd'))
  {
    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'exit');
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
