<?php
// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once "$db_objects";

class BigIPsshConnection extends SshConnection {

    // ------------------------------------------------------------------------------------------------
    public function do_connect() {
        try {
            parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        } catch (SmsException $e) {
            // try the alternate port
            if (! empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback)) {
                parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
            }
        }
        // Manage password or auto connection (ssh keys)
        unset($tab);
        $tab[0] = 'assword';
        $tab[1] = 'PASSCODE';
        $tab[2] = '#';
        $tab[3] = '$';
        $tab[4] = 'Permission denied';

        $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
        if ($index == 0 || $index == 1) {
            $this->sendCmd(__FILE__ . ':' . __LINE__, "{$this->sd_passwd_entry}");
        }
        if ($index == 4) {
            throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
        }

        echo "SSH connection established to {$this->sd_ip_config}\n";

        $this->do_post_connect();
        $this->do_store_prompt();
        $this->do_start(); // ??
    }

    // ------------------------------------------------------------------------------------------------
    public function do_start() {
        $this->setParam('suppress_echo', true);
        // $this->setParam ( 'suppress_prompt', true );
        // $this->setParam ( 'newline_dos', true );
    }

    // ------------------------------------------------------------------------------------------------
    public function do_store_prompt() {
        $this->prompt = trim($this->last_result);

        // Remove Escape terminal sequence if any
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/[\x03|\x1a]/', '', $this->prompt);
        $this->prompt = preg_replace('/.*\n/', '', $this->prompt);

        echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
    }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function f5_bigip_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null) {
    global $sms_sd_ctx;

    $sms_sd_ctx = new BigIPsshConnection($sd_ip_addr, $login, $passwd, $port_to_use);

    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function f5_bigip_disconnect() {
    global $sms_sd_ctx;
    $sms_sd_ctx = null;
    return SMS_OK;
}
?>
