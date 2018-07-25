<?php

// Transfer the configuration file on the router
// First try to use SCP then TFTP

require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';
require_once load_once('netconf_generic', 'common.php');
require_once load_once('netconf_generic', 'netconf_generic_connect.php');
require_once load_once('netconf_generic', 'apply_errors.php');
require_once "$db_objects";

/**
 * Apply the configuration using tftp (failover line by line)
 * @param string  $configuration	configuration to apply
 * @param boolean $copy_to_startup	copy in startup-config+reboot instead of running-config+write mem
 */
function netconf_generic_apply_conf($configuration) {
    global $sdid;
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $apply_errors;
    global $SMS_OUTPUT_BUF;

    $network = get_network_profile();
    $SD = &$network->SD;


    lock();

    sendexpectone(__FILE__.':'.__LINE__,$sms_sd_ctx, $configuration);
    save_result_file($configuration, 'conf.applied');
    if(!analyze_answer($sendexpect_result)){
    	discard_changes();
    	return ERR_SD_CMDFAILED;
    }

    $commit_config = PATTERNIZETEMPLATE("commit_rpc.tpl");
    sendexpectone(__FILE__.':'.__LINE__,$sms_sd_ctx, $commit_config);
    $SMS_OUTPUT_BUF .= $sendexpect_result;
    if(!analyze_answer($sendexpect_result)){
    	return ERR_SD_CMDFAILED;
    }

	sleep(2); //wait 2 seconds to have time to start the commit
	
    unlock();

    foreach ($apply_errors as $apply_error)
    {
    	if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
    	{
    		$xml_result = new SimpleXMLElement ( $SMS_OUTPUT_BUF );

    		$error_xml = $xml_result->asXML ();

    		$tagError [0] = "rpc-error";
    		$tagError [1] = "error-severity";

    		$error_severity = array_search ( $tagError , $error_xml);
    		if ($error_severity != null && $error_severity == "error")
    		{
    		save_result_file($SMS_OUTPUT_BUF, "conf.error");
    		sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
    		}

    		return ERR_SD_CMDFAILED;
    	}
    }

    save_result_file("No error found during the application of the configuration", "conf.error");

    return SMS_OK;
}

function analyze_answer($answer) {
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "--ANALYZE ANSWER--\n");
	//check if the answer contains ><ok/></rpc-reply>
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "Received answer: ".$answer."\n");
	if(strpos($answer, '><ok/></rpc-reply>') !== false){
		sms_log_debug(15,__FILE__ . ':' . __LINE__ . "Answer contains OK -> return true\n");
		return true;
	}else{
		sms_log_debug(15,__FILE__ . ':' . __LINE__ . "Answer contains error -> return false\n");
		sms_log_error(__FILE__ . ':' . __LINE__ . ": Answer contains error : ".$answer."\n");
		return false;
	}
}

function discard_changes() {
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "==== DISCARD CHANGES ====\n");
    if(!exec_template("discard_change_rpc.tpl")){
    	return ERR_SD_CMDFAILED;
    }
}

function lock() {
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "==== LOCK ====\n");
    if(!exec_template("lock_candidate_rpc.tpl")){
    	return ERR_SD_CMDFAILED;
    }
}

function unlock() {
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "==== UNLOCK ====\n");
    if(!exec_template("unlock_candidate_rpc.tpl")){
    	return ERR_SD_CMDFAILED;
    }
}

function exec_template($template) {
    global $sdid;
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $apply_errors;
    global $SMS_OUTPUT_BUF;

    $network = get_network_profile();
    $SD = &$network->SD;
    
	sms_log_debug(15,__FILE__ . ':' . __LINE__ . "Template: ".$template."\n");
	
    $tpl = PATTERNIZETEMPLATE($template);
    sendexpectone(__FILE__.':'.__LINE__,$sms_sd_ctx, $tpl);
    return analyze_answer($sendexpect_result);
}
?>
