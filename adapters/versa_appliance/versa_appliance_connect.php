<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once "$db_objects";

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function versa_appliance_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new VersaappliancesshConnection($sd_ip_addr, $login, $passwd, $port_to_use);
  return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function versa_appliance_disconnect()
{
global $sms_sd_ctx;
  try
  {
    $sms_sd_ctx->SendCmd(__FILE__ . ':' . __LINE__, "exit");
    $sms_sd_ctx->SendCmd(__FILE__ . ':' . __LINE__, "exit");
    $sms_sd_ctx->SendCmd(__FILE__ . ':' . __LINE__, "exit");
  }
  catch (Exception | Error $e)
  {
    // ignore errors
  }

  $sms_sd_ctx = null;
  return SMS_OK;
}

class VersaappliancesshConnection extends SshConnection
{
	public function do_post_connect()
	{
    	unset($tab);
    	$tab[0] = '#';
    	$tab[1] = '$';
    	$tab[2] = '>';
    	$index=$this->expect(__FILE__.':'.__LINE__, $tab);

    	if ($index == 0)
		{
			//echo "Enable CLI mode\n";
			$this->sendCmd( __FILE__ . ':' . __LINE__, "cli" );
			$index=$this->expect(__FILE__.':'.__LINE__, $tab);
			if ($index == 2)
			{
				//echo "CLI mode enabled\n";
				//echo "Change screen length & width to unlimited\n";
				$this->sendCmd( __FILE__ . ':' . __LINE__, "set screen length 0" );
				$this->sendCmd( __FILE__ . ':' . __LINE__, "set screen width 0" );
				$index=$this->expect(__FILE__.':'.__LINE__, $tab);
				if ($index == 2)
				{
					//echo "Screen size is changed: you are now ready to configure versa appliance\n";
				}
				else
				{
					throw new SmsException("Not able to change screen resolution", ERR_SD_CONNREFUSED);
				}
			}
			else
			{
				throw new SmsException("Not able to activate cli mode", ERR_SD_CONNREFUSED);
			}
		}
	}

	public function sendexpectone($origin, $cmd, $prompt='lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
	{
	    if ($prompt == 'lire dans sdctx')
	    {
	      $prompt = $this->prompt;
	      if (is_null($prompt))
	      {
	        throw new SmsException("no prompt defined for {$this->connectString}", ERR_LOCAL_PHP, $origin);
	      }
	    }

	    $this->sendCmd($origin, $cmd);

	    unset($tab);
	    $tab[0] = '[ok][';
	    $tab[1] = $prompt;
	    $expectRes= $this->expect($origin, $tab, $delay, $display_error);
	    //echo "Format result by removing header and footer\n";
	    $result = $this->last_result;
	    $posCmd = strpos($result, $cmd);
	    if ($posCmd === false) {
	    	//echo "Cmd not found in the result, skip this step\n";
		} else {
		    $result = substr($result, $posCmd+strlen($cmd));
		    //echo "Cmd found in the result, cmd removed from the result\n";
		}
		$posFooter = strpos($result, $tab[0]);
		if ($posFooter === false) {
	    	//echo "Footer not found in the result, skip this step\n";
		} else {
		    $result = substr($result, 0, $posFooter);
		    //echo "Footer found in the result, footer removed from the result\n";
		}

	    return $result;
  }

}

?>