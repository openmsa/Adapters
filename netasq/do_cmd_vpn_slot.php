<?php

require_once 'smsd/sms_common.php';

require_once load_once('netasq', 'netasq_connect.php');
require_once load_once('netasq', 'netasq_configuration.php');
require_once load_once('netasq', 'apply_errors.php');
require_once "$db_objects";

global $sms_sd_ctx;

$params = preg_split("#\r\n#", $optional_params);
$pos_vpn_id =trim($params[0]);

try {
	debug_dump($pos_vpn_id, "POS");
	netasq_connect();
	$ret = delete_vpn_slot($pos_vpn_id);
	netasq_disconnect();
}
catch (SmsException $e) {
	throw $e;
}
return $ret;

  function delete_vpn_slot($pos_vpn_id)
  {
  	global $SD;
    global $sms_sd_ctx;
    global $sdid;
    global $sms_csp;

    try
    {
	  
      $cmd = 'config ipsec policy gateway list slot=1 global=1 sort=1';
      $slot_lines = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');
      if (is_error($slot_lines, $cmd) === true)
      {
        sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$slot_lines\n");
        return ERR_SD_CMDFAILED;
      }

      $positions = array();
      $line = get_one_line($slot_lines);
	  
	  $i = 0;
	  // Get slot id matching with VPN service id
      while ($line !== false)
      {
        if ((strpos($line, 'code=') === false) && (strpos($line, 'SRPClient>') === false) &&(strpos($line, $pos_vpn_id) !== false))
        {
			$line = trim($line);
			$sa = parse_vpn_line($line);
		  
			$positions[$i] = $sa['position'];
			$i++;
		}
		$line = get_one_line($slot_lines);
	  }
	  
	  // Revert tab to delete first slot with highest id for no reorder
	  $reversed_pos = array_reverse($positions);
	  if (!empty($reversed_pos)){
	  	sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'modify on force');
	    foreach ($reversed_pos as $pos){
			$remove_cmd = 'config ipsec policy gateway remove slot=1 position='.$pos.' global=1';
			$remove_lines = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $remove_cmd, 'SRPClient>');
			if (is_error($sa_lines, $cmd) === true)
			{
				sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$sa_lines\n");
				return ERR_SD_CMDFAILED;
			}
		}
		sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'config ipsec activate');
		sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'modify off');
	  }
    }catch (SmsException $e)
    {
	  throw $e;
    }
    return SMS_OK;
  }

  function parse_vpn_line(&$line)
  {
    $records = array();
	//position=1 state="on" local="Global_LANVPN" remote="Global_SNCM125VPN36_ResauProduction" peer="Global_SNCM125VPN36" conf="VPN36"
    $pattern = "@(?<position>[\w]+)=(?<value>[A-Za-z0-9_()./:-]+)*@";
    if (preg_match_all($pattern, $line, $records_tmp) > 0) {
      for ($i = 0; $i < sizeof($records_tmp[0]); $i++) {
          $records[$records_tmp[1][$i]] = $records_tmp[2][$i];
      }
    }

    return $records;
  }

?>
