<?php

require_once 'smsd/sms_common.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_user_message.php';
require_once load_once ( 'cisco_ios_xr', 'common.php' );
require_once load_once ( 'cisco_ios_xr', 'cisco_ios_xr_connect.php' );
require_once load_once ( 'cisco_ios_xr', 'apply_errors.php' );

require_once "$db_objects";

define('DELAY', 200000);

class cisco_ios_xr_restore_configuration {
	var $conf_path; // Path for previous stored configuration files
	var $sdid; // ID of the SD to update
	var $sd; // Current SD
	var $running_conf; // Current configuration of the router
	var $previous_conf_list; // Previous generated configuration loaded from files
	var $conf_list; // Current generated configuration waiting to be saved
	var $addon_list; // List of managed addon cards
	var $fmc_repo; // repository path without trailing /
	var $fmc_ent; // entities path without trailing /
	var $runningconf_to_restore; // running conf retrieved from SVN /

	// ------------------------------------------------------------------------------------------------
	/**
	 * Constructor
	 */
	function __construct($sdid) {
		// $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
		$this->sdid = $sdid;
		// $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
		// $this->fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];

		$net = get_network_profile ();
		$this->sd = &$net->SD;
	}

	// ------------------------------------------------------------------------------------------------
	/**
	 */
	function generate_from_old_revision($revision_id) {
		echo ("generate_from_old_revision revision_id: $revision_id\n");
		$this->revision_id = $revision_id;

		$get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
		echo ($get_saved_conf_cmd . "\n");

		$ret = exec_local ( __FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output );
		if ($ret !== SMS_OK) {
			echo ("no running conf found\n");
			return $ret;
		}

		$res = array_to_string ( $output );

		// replace hidden credentials in SVN... otherwise we lose connection
		$patterns = array ();

		// SHOULD MATCH:
		// enable secret 5 $1$9008$GsTgFNtas61aKLmM6Fpg6.
		// enable secret 5
		$patterns [0] = '/enable secret 5\s*\S*\s*\n/';

		// SHOULD MATCH:
		// username cisco password 7
		// xx (not matching)
		// username admin password 7 03055F060F019
		// username CVCmu59995 password kUdbcmNwftYNT1RD encrypted privilege 0
		// username cisco password dfeaf10390e560aea745ccba53e044ed level 15 encrypted
		// SHOULD NOT MATCH
		// username NCO-SCP privilege 15 password 7 023E1D0E5A56597475
		// username cca privilege 15 password 7 0307580A
		$patterns [1] = "/username\s+\S+\s+password[ ]+\S*[ ]*\S*[ ]*\S*[ ]*[ ]*\S*[ ]*\S*\n/";

		$patterns [2] = "/OK\s/";
		$patterns [3] = "/Current configuration+.*\n/";

		$replacements = array ();
		$replacements [0] = "!\n";
		$replacements [1] = "!\n";

		$replacements [2] = "!";
		$replacements [3] = "!\n";

		$this->runningconf_to_restore = preg_replace ( $patterns, $replacements, $res );

		$enable_line = "enable secret " . $this->sd->SD_PASSWD_ADM . "\n";
		$username_line = "username " . $this->sd->SD_LOGIN_ENTRY . " password " . $this->sd->SD_PASSWD_ENTRY . "\n \n";
		$this->runningconf_to_restore = $enable_line . $username_line . $this->runningconf_to_restore;

		return SMS_OK;
	}

	function restore_conf() {
		global $apply_errors;
		global $default_disk;

		global $sms_sd_ctx;
		$ret = SMS_OK;

		// destination for configuration file on device
		$dst_disk = $default_disk;

		echo "SCP mode configuration\n";

		// Request flash space on router
		$file_name = "{$this->sdid}.cfg";
		$full_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;

		$ret = save_file ( $this->runningconf_to_restore, $full_name );
		if ($ret !== SMS_OK) {
			return $ret;
		}
		$ret = save_result_file ( $this->runningconf_to_restore, 'conf.applied' );
		if ($ret !== SMS_OK) {
			return $ret;
		}
		try {
			$ret = scp_to_router ( $full_name, $file_name );
			if ($ret === SMS_OK) {
				// SCP OK

				echo "Load configuration to restore\n";
				$ERROR_BUFFER = '';

				sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "conf exclusive", "(config)#", DELAY);
				/*
					RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#load disk0:UBI153.cfg
					Loading.
					1263 bytes parsed in 1 sec (1246)bytes/sec
				 or
					RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#load disk0:toto.txt
					Loading.
					Syntax/Authorization errors in one or more commands. Please use 'show configuration failed load [detail]' to view errors.

					6 bytes parsed in 1 sec (5)bytes/sec
				*/
				$line = "load $dst_disk:$file_name";
				$SMS_OUTPUT_BUF = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, "(config)#", DELAY);

				if (preg_match("@Syntax/Authorization errors@", $SMS_OUTPUT_BUF, $matches) > 0) {
                    $ERROR_BUFFER .= "!";
                    $ERROR_BUFFER .= "\n";
                    $ERROR_BUFFER .= $line;
                    $ERROR_BUFFER .= "\n";
                    $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
                    $ERROR_BUFFER .= "\n";

                    $line = "show configuration failed load detail";
                    $SMS_OUTPUT_BUF = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, ")#", DELAY);

                    $ERROR_BUFFER .= $line;
                    $ERROR_BUFFER .= "\n";
                    $ERROR_BUFFER .= $SMS_OUTPUT_BUF;
                    $ERROR_BUFFER .= "\n";

                    $ret = ERR_RESTORE_FAILED;
                }
                else {
                    // Commit configuration at revision r$this->revision_id

                    /*
                    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#commit replace comment "load from disk0:UBI153.cfg"
                    This commit will replace or remove the entire running configuration. This
                    operation can be service affecting.
                    Do you wish to proceed? [no]: yes

                    RP/0/RP0/CPU0:DEV-NEC-CISCO-IOS-XR-9000(config)#end
                    */
                    $line = "commit replace comment \"MSA: restore conf r$this->revision_id\"";
                    $SMS_OUTPUT_BUF .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $line, "proceed? [no]:", DELAY);

                    $SMS_OUTPUT_BUF .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "yes", ")#", DELAY);

                    foreach ($apply_errors as $apply_error) {
                        if (preg_match($apply_error, $SMS_OUTPUT_BUF, $matches) > 0) {
                            $ERROR_BUFFER .= "!";
                            $ERROR_BUFFER .= "\n";
                            $ERROR_BUFFER .= $line;
                            $ERROR_BUFFER .= "\n";
                            $ERROR_BUFFER .= $apply_error;
                            $ERROR_BUFFER .= "\n";
                            $SMS_OUTPUT_BUF = '';

                            $ret = ERR_RESTORE_FAILED;
                        }
                    }
                } // end of: else (preg_match("@Syntax/Authorization errors@", ...)

                // if load or commit failed, we can have message to commit then at end command
                unset($tab);
                $tab[0] = $sms_sd_ctx->getPrompt();
                $tab[1] = "commit them before exiting(yes/no/cancel)? [cancel]:";

                $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $tab, DELAY);
                if ($index === 1) {
                    sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "no", "#", DELAY);
                }

                // Refetch the prompt cause it can change during the apply conf
                extract_prompt();

                // Exit from config mode
                unset($tab);
                $tab[0] = $sms_sd_ctx->getPrompt();
                $tab[1] = ")#";
                $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab, DELAY);
                $SMS_OUTPUT_BUF = $sendexpect_result;
                for ($i = 1; ($i <= 10) && ($index === 1); $i++) {
                    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "exit", $tab, DELAY);
                    $SMS_OUTPUT_BUF .= $sendexpect_result;
                }

                if (!empty($ERROR_BUFFER)) {
                    save_result_file($ERROR_BUFFER, "conf.error");
                    $SMS_OUTPUT_BUF = $ERROR_BUFFER;
                    sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
                    return ERR_SD_CMDFAILED;
                }
                else {
                    save_result_file("No error found during the application of the configuration", "conf.error");
                }

				return $ret;
			}
			else {
				// SCP error: scp_to_router ( ... ) !== SMS_OK
				sms_log_error ( __FILE__ . ':' . __LINE__ . ":SCP Error $ret\n" );
				return $ret;
			}
		} catch ( Exception | Error $e ) {
			if (strpos ( $e->getMessage (), 'connection failed' ) !== false) {
				return ERR_SD_CONNREFUSED;
			}
			sms_log_error ( __FILE__ . ':' . __LINE__ . ":SCP Error $ret\n" );
			return ERR_SD_SCP;
		} // end try scp_to_router ( $full_name, $file_name );
		return $ret;
	}
	// end of function restore_conf()
}

?>
