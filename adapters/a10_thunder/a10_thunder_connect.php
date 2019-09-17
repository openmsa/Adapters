<?php
// Open/close the session dialog with the router
// For communication with the router
require_once 'smsd/sms_common.php';
require_once 'smsd/expect.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/telnet_connection.php';
require_once "$db_objects";

class thunderSshConnection extends SshConnection {

    // ------------------------------------------------------------------------------------------------
    public function do_connect() {
        try {
            parent::connect("ssh -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
        } catch (SmsException $e) {
            // try the alternate port
            if (! empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback)) {
                parent::connect("ssh -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}'");
            }
        }

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

        unset($tab);
        $tab[0] = '#';
        $tab[1] = '>';
        $this->expect(__FILE__ . ':' . __LINE__, $tab);
        echo "SSH connection established to {$this->sd_ip_config}\n";

        $this->do_post_connect();
        $this->do_store_prompt();
        $this->do_start();
    }

    // ------------------------------------------------------------------------------------------------
    public function do_post_connect() {
        $this->sendCmd(__FILE__ . ':' . __LINE__, "enable");
        unset($tab);
        $tab[0] = '#';
        $tab[1] = 'Password';
        $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);

        if ($index == 1) {
            unset($tab);
            $tab[0] = '#';
            $tab[1] = 'Password';
            $tab[2] = '>';
            $this->sendCmd(__FILE__ . ':' . __LINE__, "");
            $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
            if ($index == 2) {
                $this->sendCmd(__FILE__ . ':' . __LINE__, "enable");
                $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
                $this->sendCmd(__FILE__ . ':' . __LINE__, "{$this->sd_admin_passwd_entry}");
                $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
                if ($index === 2) {
                    throw new SmsException("Connection error for {$this->sd_ip_config}", ERR_SD_ADM_AUTH);
                }
            }
        }
        sendexpectnobuffer(__FILE__ . ':' . __LINE__, $this, "terminal length 0", '#');
        sendexpectnobuffer(__FILE__ . ':' . __LINE__, $this, "terminal width 0", '#');
    }
    // ------------------------------------------------------------------------------------------------
    public function do_store_prompt() {
        $this->sendCmd(__FILE__ . ':' . __LINE__, '');
        unset($tab);
        $tab[0] = '#';
        $tab[1] = '$';
        $tab[2] = '>';
        $this->expect(__FILE__ . ':' . __LINE__, $tab);
        $this->prompt = trim($this->last_result);

        // Remove Escape terminal sequence if any
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $this->prompt);
        $this->prompt = preg_replace('/[\x03|\x1a]/', '', $this->prompt);
        $this->prompt = preg_replace('/.*\n/', '', $this->prompt);

        echo "Prompt found: {$this->prompt} for {$this->sd_ip_config}\n";
    }
    // ------------------------------------------------------------------------------------------------
    public function do_start() {
        // $this->setParam('suppress_echo', true);
        $this->setParam('suppress_prompt', true);
        $this->setParam('newline_dos', true);
    }
}

class thunderTelnetConnection extends TelnetConnection {

    public function do_connect() {
        if (! empty($this->sd_management_port) && $this->sd_management_port != 22) {
            parent::connect("telnet {$this->sd_ip_config} $this->sd_management_port");
        } else {
            parent::connect("telnet {$this->sd_ip_config}");
        }
        if (! empty($this->sd_management_port) && $this->sd_management_port != 22) {
            sleep(5);
            $this->sendCmd(__FILE__ . ':' . __LINE__, "");
        }
        unset($tab);
        $tab[0] = ">";
        $tab[1] = "#";
        $tab[2] = "login:";
        $tab[3] = "assword:";
        $tab[4] = "PASSCODE:";
        $tab[5] = "none set";

        try {
            $index = $this->expect(__FILE__ . ':' . __LINE__, $tab, 3000);
        } catch (SmsException $e) {
            throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
        }

        $net_profile = get_network_profile();
        $sd = &$net_profile->SD;

        if ($index === 2) {
            $this->sendCmd(__FILE__ . ':' . __LINE__, "{$this->sd_login_entry}");
            $index = $this->expect(__FILE__ . ':' . __LINE__, $tab, 10000);
        }
        if ($index === 3 || $index === 4) {
            unset($tab);
            $tab[0] = ">";
            $tab[1] = "#";
            $tab[2] = "closed by foreign host.";
            $tab[3] = "assword:";
            $tab[4] = "PASSCODE:";

            $this->sendCmd(__FILE__ . ':' . __LINE__, "{$this->sd_passwd_entry}");
            $index = $this->expect(__FILE__ . ':' . __LINE__, $tab, 10000);
        }
        if ($index === 2) {
            $this->disconnect();
            throw new SmsException("Connection error for {$this->sd_ip_config}", ERR_SD_AUTH);
        }
        echo "Telnet connection established to {$this->sd_ip_config}\n";

        if ($index === 0) {
            $this->do_post_connect();
        }
        $this->do_store_prompt();
        $this->do_start();
    }

    public function do_post_connect() {
        unset($tab);
        $tab[0] = '#';
        $tab[1] = '>';
        $this->sendCmd(__FILE__ . ':' . __LINE__, "");
        $this->expect(__FILE__ . ':' . __LINE__, $tab);

        $this->sendCmd(__FILE__ . ':' . __LINE__, "enable");
        unset($tab);
        $tab[0] = '#';
        $tab[1] = 'Password';
        $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);

        if ($index == 1) {
            unset($tab);
            $tab[0] = '#';
            $tab[1] = 'Password';
            $tab[2] = '>';
            $this->sendCmd(__FILE__ . ':' . __LINE__, "");
            $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
            if ($index == 2) {
                $this->sendCmd(__FILE__ . ':' . __LINE__, "enable");
                $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
                $this->sendCmd(__FILE__ . ':' . __LINE__, "{$this->sd_admin_passwd_entry}");
                $index = $this->expect(__FILE__ . ':' . __LINE__, $tab);
                if ($index === 2) {
                    throw new SmsException("Connection error for {$this->sd_ip_config}", ERR_SD_ADM_AUTH);
                }
            }
        }
        sendexpectnobuffer(__FILE__ . ':' . __LINE__, $this, "terminal length 0", "#");
        sendexpectnobuffer(__FILE__ . ':' . __LINE__, $this, "terminal width 0", "#");
    }

    public function do_start() {
        // $this->setParam('suppress_echo', true);
        $this->setParam('suppress_prompt', true);
        $this->setParam('newline_dos', true);
    }
}

// ------------------------------------------------------------------------------------------------
// return false if error, true if ok
function a10_thunder_connect($sd_ip_addr = null, $login = null, $passwd = null, $adminpasswd = null, $port_to_use = null) {
    global $sms_sd_ctx;
    try {
        $sms_sd_ctx = new thunderSshConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
    } catch (SmsException $e) {
        $sms_sd_ctx = new thunderTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_to_use);
    }
    return SMS_OK;
}

// ------------------------------------------------------------------------------------------------
// Disconnect
function a10_thunder_disconnect() {
    global $sms_sd_ctx;
    $sms_sd_ctx = null;
    return SMS_OK;
}
?>
