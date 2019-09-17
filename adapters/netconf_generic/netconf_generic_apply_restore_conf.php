<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP
require_once 'smsd/sms_common.php';
require_once load_once ( 'netconf_generic', 'netconf_generic_connect.php' );
require_once load_once ( 'netconf_generic', 'apply_errors.php' );
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * 
 * @param string $configuration
 *        	to apply
 * @param boolean $copy_to_startup
 *        	in startup-config+reboot instead of running-config+write mem
 */
function netconf_generic_apply_restore_conf($configuration) {
	global $sdid;
	global $sms_sd_ctx;
	global $sendexpect_result;
	global $apply_errors;
	global $SMS_OUTPUT_BUF;
	global $SD;
	
	$ipaddr = $sms_sd_ctx->getIpAddress ();
	$login = $sms_sd_ctx->getLogin ();
	$passwd = $sms_sd_ctx->getPassword ();
	
	if (strlen ( $configuration ) === 0) {
		return SMS_OK;
	}
	
	if (strlen ( $configuration ) !== 0) {
		// Validate XML File
		try {
			$configLineArray = explode ( "\n", $configuration );
			
			$configTmp = "";
			$pos = strpos ( $configLineArray [0], "OK" );
			if ($pos === false) {
				$configTmp = $configuration;
			} else {
				unset ( $configLineArray [0] );
				foreach ( $configLineArray as $line ) {
					$configTmp .= $line . "\n";
				}
			}
		} catch ( Exception | Error $e ) {
			sms_log_error ( __FILE__ . ':' . __LINE__ . ': ' . $e->getMessage () . "\n" );
			return ERR_CONFIGURATION_INVALID;
		}
	}
	
	debug_dump ( $configTmp, 'CONFIG TO APPLY' );
	
	$file_name = "$sdid.cfg";
	
	// Create the file
	$local_file_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;
	if (file_put_contents ( $local_file_name, $configTmp ) === false) {
		sms_log_error ( __FILE__ . ':' . __LINE__ . ": file_put_contents(\"$local_file_name\", \"...\") failed\n" );
		unlink ( $local_file_name );
		return ERR_LOCAL_FILE;
	}
	
	$src = $local_file_name;
	$dst = "/config/config-from-msa.cfg";
	
	$ret_scp = exec_local ( __FILE__ . ':' . __LINE__, "/opt/sms/bin/sms_scp_transfer -v 2 -s $src -d $dst -l $login -a $ipaddr -p $passwd", $output );
	unlink ( $local_file_name );
	
	if ($ret_scp !== SMS_OK) {
		return $ret_scp;
	}
	
	$scp_ok = false;
	foreach ( $output as $line ) {
		if (strpos ( $line, 'SMS-CMD-OK' ) !== false) {
			$scp_ok = true;
			break;
		}
	}
	
	if ($scp_ok === false) {
		foreach ( $output as $line ) {
			sms_log_error ( $line );
		}
		return ERR_SD_SCP;
	}
	
	// Save the configuration applied on the router
	save_result_file ( $configTmp, 'conf.applied' );
	
	$sms_sd_ctx->sendCmd ( __FILE__ . ':' . __LINE__, "edit" );
	$sms_sd_ctx->sendCmd ( __FILE__ . ':' . __LINE__, "load override /config/config-from-msa.cfg" );
	
	$sendexpect_result = '';
	$SMS_OUTPUT_BUF = '';
	
	$tab [0] = 'load complete';
	$tab [1] = $sms_sd_ctx->getPrompt ();
	
	$index = $sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, $tab );
	$SMS_OUTPUT_BUF .= $sendexpect_result;
	
	switch ($index) {
		case 0 :
			// commit complete
			$sms_sd_ctx->sendCmd ( __FILE__ . ':' . __LINE__, "commit" );
			unset ( $tab );
			$tab [0] = 'commit complete';
			$tab [1] = 'error: cannot commit an empty configuration';
			$tab [2] = $sms_sd_ctx->getPrompt ();
			$index = $sms_sd_ctx->expect ( __FILE__ . ':' . __LINE__, $tab );
			
			$SMS_OUTPUT_BUF .= $sendexpect_result;
			
			if ($index !== 0) {
				return ERR_SD_CMDFAILED;
			}
			break;
	}
	
	save_result_file ( $SMS_OUTPUT_BUF, "conf.error" );
	foreach ( $apply_errors as $apply_error ) {
		if (preg_match ( $apply_error, $SMS_OUTPUT_BUF ) > 0) {
			sms_log_error ( __FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n" );
			return ERR_SD_CMDFAILED;
		}
	}
	
	return SMS_OK;
}

?>
