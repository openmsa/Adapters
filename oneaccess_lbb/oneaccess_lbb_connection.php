<?php
/*
 
 * Available global variables
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        sd_info structure
 *  $sms_csp            pointer to csp context to send response to user
 *  $sdid
 *  $sms_module         module name (for patterns)
 */

// For communication with the router
require_once 'smsd/ssh_connection.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('oneaccess_lbb', 'common.php');
require_once "$db_objects";


function oneaccess_lbb_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null)
{
	global $sms_sd_ctx;


	echo "===============   oneaccess_lbb_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) \n";

	try{
	$sms_sd_ctx = new OneAccessConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
	$sms_sd_ctx->setParam("PROTOCOL", "SSH");
	} catch (SmsException $e) {
		return ERR_SD_CONNREFUSED;
	}
	return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function oneaccess_lbb_disconnect()
{
	global $sms_sd_ctx;
	if(is_object($sms_sd_ctx) && $sms_sd_ctx != null)
	{
		$tab[0] = ')>';
		$tab[1] = $sms_sd_ctx->getPrompt();
		 
		$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
		$i = 0;
		while($index == 0 && $i < 10)
		{
		    $i++;
			sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'end', $tab);
		}
		$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, 'exit');
	}
	$sms_sd_ctx = null;
	return SMS_OK;
}


class OneAccessConnection extends SshConnection
{
	var $prompt;

	public function do_connect()
	{
		try
		{
			parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password '{$this->sd_login_entry}@{$this->sd_ip_config}'");
		}
		catch (SmsException $e)
		{
			// try the alternate port
			if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback))
			{
				parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password '{$this->sd_login_entry}@{$this->sd_ip_config}'");
			}
		}

		$this->setParam("newline_dos", true);

		// Manage password or auto connection (ssh keys)
		unset($tab);
		$tab[0] = 'username:';
		$tab[1] = 'assword:';
		$tab[2] = '#';
		$tab[3] = '>';
		//echo " *** PASSWORD *** ".$this->sd_passwd_entry."\n_";


		$index = $this->expect(__FILE__.':'.__LINE__, $tab);
		if ($index == 0)
		{
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_login_entry}");
			$index = $this->expect(__FILE__.':'.__LINE__, $tab);
		}
		if($index == 1)
		{
			$this->sendCmd(__FILE__.':'.__LINE__, "{$this->sd_passwd_entry}");
			$index = $this->expect(__FILE__.':'.__LINE__, $tab);
		}

		if($index < 2)
		{
			throw new SmsException("Connection Failed, still ask for login or password", ERR_SD_CONNREFUSED);
		}

		//$this->sendCmd(__FILE__.':'.__LINE__, "terminal datadump");

		echo "SSH connection established to {$this->sd_ip_config}\n";
		$this->do_post_connect();
		$this->do_store_prompt();

		echo "Prompt found: {$this->prompt}\n";

		// No more need prompt on commands:
		$this->setParam("suppress_prompt", 1);
		$this->setParam("suppress_echo", 1);
		
		//remove pagination
		//$this->sendCmd(__FILE__.':'.__LINE__, "paginate false");
		//$index = $this->expect(__FILE__.':'.__LINE__, $tab);
	}

	//Features re-definition : do_post_connect() and do_store_prompt();

  public function do_post_connect() {
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $this->sendCmd(__FILE__.':'.__LINE__, "");
    $index = $this->expect(__FILE__.':'.__LINE__, $tab);
  }

  public function do_store_prompt() {
    $this->sendCmd(__FILE__.':'.__LINE__, "");
    unset($tab);
    $tab[0] = '#';
    $tab[1] = '$';
    $tab[2] = '>';
    $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    $this->prompt = trim($this->last_result);

    // Remove Escape terminal sequence if any
    $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "",$this->prompt);
    $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "",$this->prompt);
    $this->prompt = preg_replace('/[\x03|\x1a]/', "", $this->prompt);

    echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
  }

}

?>
