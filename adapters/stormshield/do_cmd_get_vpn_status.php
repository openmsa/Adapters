<?php

require_once 'smsd/sms_common.php';

require_once load_once('stormshield', 'netasq_connect.php');
require_once load_once('stormshield', 'netasq_configuration.php');
require_once load_once('stormshield', 'apply_errors.php');
require_once "$db_objects";

global $sms_sd_ctx;

$params = preg_split("#\r\n#", $optional_params);
$vpn_id =trim($params[0]);

try {
	netasq_connect();
	$ret = get_vpn_status($vpn_id);
	netasq_disconnect();
}
catch (SmsException $e) {
	throw $e;
}
return $ret;

  function get_vpn_status($vpn_id)
  {
  	global $SD;
    global $sms_sd_ctx;
    global $sdid;
    global $sms_csp;

    try
    {
      $cmd = 'MONITOR GETSPD';
      $spd_lines = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');
      if (is_error($spd_lines, $cmd) === true)
      {
        sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$spd_lines\n");
        return ERR_SD_CMDFAILED;
      }

      $cmd = 'MONITOR GETSA';
      $sa_lines = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');
      if (is_error($sa_lines, $cmd) === true)
      {
        sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$sa_lines\n");
        return ERR_SD_CMDFAILED;
      }

      $result_array = array();
      $spd_array = array();
      $sa_array = array();

      $line = get_one_line($spd_lines);
      while ($line !== false)
      {
        if ((strpos($line, 'code=') === false) && (strpos($line, 'SRPClient>') === false) && (strpos($line, $cmd) === false) && (strpos($line, '127.0.0.0') === false))
        {
          $line = trim($line);
          $spd = parse_vpn_line($line);
          if (!empty($spd))
          {
            $spd_array[$spd['reqid']] = $spd;
          }
        }
        $line = get_one_line($spd_lines);
      }

      $line = get_one_line($sa_lines);
      while ($line !== false)
      {
        if ((strpos($line, 'code=') === false) && (strpos($line, 'SRPClient>') === false) && (strpos($line, $cmd) === false) && (strpos($line, 'state="mature"') !== false))
        {
          $line = trim($line);
          $sa = parse_vpn_line($line);
          if (!empty($sa))
          {
            $sa_array[$sa['reqid']] = $sa;
          }
        }
        $line = get_one_line($sa_lines);
      }

		build_ph1_ph2_from_cli($sdid, $ph1_array_cli, $ph2_array_cli, $spd_array, $sa_array);

      $result_array['monitor_spd'] = $ph1_array_cli;
      $result_array['monitor_sa'] = $ph2_array_cli;
      sms_send_user_ok($sms_csp, $sdid, json_encode($result_array));
    }
    catch (SmsException $e)
    {
	  throw $e;
    }
    return SMS_OK;
  }

  function parse_vpn_line(&$line)
  {
    $records = array();

    $pattern = "@(?<name>[\w]+)=(?<value>[A-Za-z0-9_()./:-]+)|(?<namestr>[\w]+)=\"(?<valuestr>[^\"]+)\"@";
    if (preg_match_all($pattern, $line, $records_tmp) > 0) {
      //it's mean that $line is a type of log
      //the next step is to write value in an array
      //in case ?<name>[\w]+)=(?<value>[A-Za-z0-9_:-]+) works, name is on $records_tmp[1][$i] and value $records_tmp[2][$i]
      //whereas when (?<namestr>[\w]+)=\"(?<valuestr>[^\"]+) works, name is on $records_tmp[3][$i] and value $records_tmp[4][$i]
      for ($i = 0; $i < sizeof($records_tmp[0]); $i++) {
        if (!empty ($records_tmp[1][$i])) {
          $records[$records_tmp[1][$i]] = $records_tmp[2][$i];
        } else {
          $records[$records_tmp[3][$i]] = $records_tmp[4][$i];
        }
      }
    }

    return $records;
  }

  function build_ph1_ph2_from_cli(&$sdid, &$ph1_array, &$ph2_array, $spd_array, $sa_array)
  {
    $ph1_array = array();
    $ph2_array = array();

    if (!empty($spd_array) && !empty($sa_array))
    {
      foreach($sa_array as $reqid => $sa)
      {
        if (!empty($spd_array[$reqid]))
        {
          $spd = $spd_array[$reqid];
          if ($spd['dir'] === 'out') // get only output direction
          {
            $ph1 = array();
            $ph1['local_id'] = $sdid;
            $ph1['peer_id'] = substr($spd['dstgwname'], 1, 6);
            $ph1['local_tunnel_end_point'] = $spd['srcgw'];
            $ph1['peer_tunnel_end_point'] = $spd['dstgw'];

            $ph1_key = "{$ph1['local_id']}-{$ph1['peer_id']}-{$ph1['local_tunnel_end_point']}-{$ph1['peer_tunnel_end_point']}";
			if (empty($ph1_array[$ph1_key]))
            {
			  $ph1['status'] = 'OK';
              $ph1_array[$ph1_key] = $ph1;
            }

            $ph2 = array();
            $ph2['peer_id'] = $ph1['peer_id'];
            $ph2['local_trafic_end_point'] = "{$spd['src']}/{$spd['srcmask']}";
            $ph2['peer_trafic_end_point'] = "{$spd['dst']}/{$spd['dstmask']}";

            $ph2_key = "{$ph2['peer_id']}-{$ph2['local_trafic_end_point']}-{$ph2['peer_trafic_end_point']}";
            if (empty($ph2_array[$ph2_key]))
            {
              $ph2['lifetime'] = $sa['lifetime'];
              $ph2['bytes'] = $sa['bytes'];
			  $ph2['status'] = 'OK';
              $ph2_array[$ph2_key] = $ph2;
            }
          }
        }
      }
    }
  }

?>
