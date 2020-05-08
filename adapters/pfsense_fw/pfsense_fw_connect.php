<?php
/*
 * 	Version: 0.1: pfsense_connect.php
 *  	Created: Jun 7, 2012
 *  	Available global variables
 *  	$sms_sd_ctx        		pointer to sd_ctx context to retreive usefull field(s)
 *  	$sms_sd_info        	sd_info structure
 *  	$sms_csp            	pointer to csp context to send response to user
 *  	$sdid
 *  	$sms_module         	module name (for patterns)
 *  	$SMS_RETURN_BUF    		string buffer containing the result
 */

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";
class PfsenseConnection extends SshConnection
{
        public function do_post_connect()
        {
                echo "***Call pfsense_fw Do_post_connect***\n";
                unset($tab);
                $tab[0] = 'Enter an option:';
                $tab[1] = 'root';         
                $result_id = $this->expect(__FILE__.':'.__LINE__, $tab);

                if($result_id === 0) {
                        $this->sendCmd(__FILE__.':'.__LINE__, "8");
                        $result_id = $this->expect(__FILE__.':'.__LINE__, $tab);
                }
                if($result_id !== 1) {
                        throw new SmsException("Connection Failed, can't enter in shell  mode", ERR_SD_CONNREFUSED);
                }

//                $this->sendexpectone(__FILE__.':'.__LINE__, "terminal length 0",'#');
        }


  public function do_store_prompt()
  {
//    parent::do_store_prompt();
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $this, '', 'root');
    $this->prompt = preg_replace('/.*\n/', '', $buffer);
   $this->prompt = trim($this->prompt);	
	echo "The prompt is ---$this->prompt---\n";
  }
 /* public function reboot()
  {
    unset($tab);
    $tab[0] = 'try later';
    $tab[1] = '(Y/N)';
    $tab[2] = 'Shutting down';
    $this->sendCmd(__FILE__ . ':' . __LINE__, "reload");
    $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);

    while ($index < 2)
    {
      switch ($index)
      {
        case 0:
          sleep(5);
          $this->sendCmd(__FILE__ . ':' . __LINE__, "reload");
          $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
          break;
        case 1:
          $this->send(__FILE__ . ':' . __LINE__, "Y");
          $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
          break;
      }
    }
  }*/
}


// return false if error, true if ok
function pfsense_fw_connect($login = null, $passwd = null, $sd_ip_addr = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new PfsenseConnection($sd_ip_addr, $login, $passwd, null, $port_to_use);

  return SMS_OK;
}

// Disconnect
// return false if error, true if ok
function pfsense_fw_disconnect()
{
global $sms_sd_ctx;
  if(is_object($sms_sd_ctx))
  {
        $tab[0] = $sms_sd_ctx->getPrompt();
	$tab[1] = 'Enter an option:';
        $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, '', $tab);
	$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'exit', $tab);
	while($index === 0){
		$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'exit', $tab);
	}
	if($index === 1){
		//Now the prompt is asking for more numeric input where we need to press 0 to disconnect the ssh
        	$sms_sd_ctx->sendCmd(__FILE__.':'.__LINE__, '0');
	}
  }
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>
