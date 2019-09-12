<?php

// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";
class JuniperSRXsshConnection extends SshConnection
{
    
    // ------------------------------------------------------------------------------------------------
    public function do_connect()
    {
        try
        {
            parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        }
        catch (SmsException $e)
        {
            // try the alternate port
            if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback))
            {
                parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
            }
        }
        // Manage password or auto connection (ssh keys)
        unset($tab);
        $tab[0] = 'assword';
        $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
        //echo "SSH connection established to {$this->sd_ip_config}\n";
    }
    
    // ------------------------------------------------------------------------------------------------
    public function do_start()
    {
        $this->setParam('suppress_echo', true);
        $this->setParam('suppress_prompt', true);
        $this->setParam('newline_dos', true);
    }
    
    // ------------------------------------------------------------------------------------------------
    public function do_store_prompt()
    {
        $this->prompt = trim($this->last_result);
        
        // Remove Escape terminal sequence if any
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/[\x03|\x1a]/', '', $this->prompt);
        $this->prompt = preg_replace('/.*\n/', '', $this->prompt);
        
        echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
    }
    // ------------------------------------------------------------------------------------------------
    public function juniper_srx_manage_menu($login, $passwd)
    {
        global $sms_sd_ctx;
        global $sdid;
        
        unset($tab);
        $tab[0] = '%';
        $tab[1] = '>';
        
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "$passwd", $tab);
        
        if ($index == 0)
        {
            
            unset($tab);
            $tab[0] = '#';
            $tab[1] = '$';
            $tab[2] = '>';
            $tab[3] = '%';
            sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "cli", $tab);
            
            $sms_sd_ctx->do_store_prompt();
        }
        else if ($index == 1)
        {
            
            $sms_sd_ctx->do_store_prompt();
        }
        
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "set cli screen-length 0", $sms_sd_ctx->getPrompt());
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "set cli screen-width 0", $sms_sd_ctx->getPrompt());
        
        return SMS_OK;
    }
    
    //sendCommand with return,  send method is just do write example prompty like yes/no:
    //public function sendCmd($origin, $cmd)
    //{
    //$this->send($origin, $cmd);
    //}
}

class JuniperSRX2sshConnection extends SshConnection
{
    
    // ------------------------------------------------------------------------------------------------
    public function do_connect()
    {
        try
        {
            parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        }
        catch (SmsException $e)
        {
            // try the alternate port
            if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback))
            {
                parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
            }
        }
        // Manage password or auto connection (ssh keys)
        unset($tab);
        $tab[0] = 'assword';
        $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
        echo "SSH connection established to {$this->sd_ip_config}\n";
    }
    
    // ------------------------------------------------------------------------------------------------
    public function do_start()
    {
        $this->setParam('suppress_echo', true);
        $this->setParam('suppress_prompt', true);
        $this->setParam('newline_dos', true);
    }
    
    // ------------------------------------------------------------------------------------------------
    public function do_store_prompt()
    {
        $this->prompt = trim($this->last_result);
        
        // Remove Escape terminal sequence if any
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/[\x03|\x1a]/', '', $this->prompt);
        $this->prompt = preg_replace('/.*\n/', '', $this->prompt);
        
        //echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
    }
    
    //sendCommand with return,  send method is just do write example prompty like yes/no:
    //public function sendCmd($origin, $cmd)
    //{
    //$this->send($origin, $cmd);
        //}
        
        // ------------------------------------------------------------------------------------------------
        public function juniper_srx_manage_menu($login, $passwd)
        {
            global $sms_sd_ctx;
            global $sdid;
            
            unset($tab);
            $tab[0] = '%';
            $tab[1] = '>';
            
            $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "$passwd\n\n", $tab);
            
            if ($index == 0)
            {
                
                unset($tab);
                $tab[0] = '#';
                $tab[1] = '$';
                $tab[2] = '>';
                $tab[3] = '%';
                sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "cli\n\n", $tab);
                
                $sms_sd_ctx->do_store_prompt();
            }
            else if ($index == 1)
            {
                
                $sms_sd_ctx->do_store_prompt();
            }
            
            sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "set cli screen-length 0", $sms_sd_ctx->getPrompt());
            sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "set cli screen-width 0", $sms_sd_ctx->getPrompt());
            
            return SMS_OK;
        }
        
}
// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function juniper_srx_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
    global $sms_sd_ctx;
    global $model_data;
    $data = json_decode($model_data, true);
    $class = $data['class'];
    
    $sms_sd_ctx = new $class($sd_ip_addr, $login, $passwd, $port_to_use);
    $sms_sd_ctx->juniper_srx_manage_menu($sms_sd_ctx->getLogin(), $sms_sd_ctx->getPassword());
    
    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function juniper_srx_disconnect()
{
    global $sms_sd_ctx;
    try
    {
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
        $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, "exit");
    }
    catch (Exception | Error $e)
    {
        // ignore errors
    }
    
    $sms_sd_ctx = null;
    return SMS_OK;
}

?>

