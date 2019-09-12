<?php
/*
 *  Available global variables
 *   $sms_sd_info       sd_info structure
 *   $sdid
 *   $sms_module        module name (for patterns)
 *   $sd_poll_elt       pointer on sd_poll_t structure
 *   $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('paloalto_generic', 'paloalto_generic_connect.php');
require_once "$db_objects";
function date_conversion($input)
{
  list ($month, $day, $year) = explode(" ", $input);

  $months = array(
      "January" => "01",
      "February" => "02",
      "March" => "03",
      "April" => "04",
      "May" => "05",
      "June" => "06",
      "July" => "07",
      "August" => "08",
      "September" => "09",
      "October" => "10",
      "November" => "11",
      "December" => "12"
  );
  $mm = $months[$month];
  $dd = substr($day, 0, 2);

  $date = "$year/$mm/$dd 00:00:00";
  return trim($date);
}

try
{
  // Connection
  paloalto_generic_connect();

  $asset = array();
  $asset_attributes = array();

  // GET ASSETS


  // Get Memory asset
  /*
   top - 06:53:53 up 18 days, 23:15,  1 user,  load average: 0.01, 0.01, 0.02
   Tasks: 106 total,   1 running, 105 sleeping,   0 stopped,   0 zombie
   Cpu(s):  2.1%us,  6.1%sy,  0.3%ni, 91.4%id,  0.0%wa,  0.0%hi,  0.0%si,  0.0%st
   Mem:   4057060k total,  3888964k used,   168096k free,    97040k buffers
   Swap:  2008084k total,        0k used,  2008084k free,  3146876k cached

   PID USER      PR  NI  VIRT  RES  SHR S %CPU %MEM    TIME+  COMMAND
   2226       20   0 1931m 1.8g 1.8g S    6 47.2   2525:16 pan_task
   2224       20   0 2012m 1.9g 1.8g S    4 47.9   1239:30 pan_comm
   14749       20   0  2384  956  724 R    2  0.0   0:00.02 top
   1       20   0  1776  556  484 S    0  0.0   0:13.33 init
   */
  $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "type=op&cmd=<show><system><resources></resources></system></show>", "result");
  $pattern = '@Mem:\s+(?<memory>\S+)\s+total@';
  if (preg_match($pattern, $buffer, $matches) > 0)
  {
    $asset['memory'] = trim($matches['memory']);
  }

  // Get Serial Number
  /*
   hostname: PA-VM
   ip-address: 10.30.19.110
   netmask: 255.255.254.0
   default-gateway: 10.30.19.254
   ipv6-address: unknown
   ipv6-link-local-address: fe80::20c:29ff:fe1c:e130/64
   ipv6-default-gateway:
   mac-address: 00:0c:29:1c:e1:30
   time: Wed Jul 16 07:10:13 2014
   uptime: 18 days, 23:31:51
   family: vm
   model: PA-VM
   serial: 007200001889
   vm-mac-base: 00:1B:17:E3:E1:00
   vm-mac-count: 256
   vm-uuid: 564D6F05-BE75-7193-0386-7BA0D61CE130
   vm-cpuid: D7060200FFFBAB1F
   vm-license: VM-300
   sw-version: 6.0.0
   global-protect-client-package-version: 0.0.0
   app-version: 410-2049
   app-release-date: unknown
   av-version: 0
   av-release-date: unknown
   threat-version: 0
   threat-release-date: unknown
   wildfire-version: 0
   wildfire-release-date: unknown
   url-filtering-version: 0000.00.00.000
   global-protect-datafile-version: 0
   global-protect-datafile-release-date: unknown
   logdb-version: 6.0.6
   platform-family: vm
   logger_mode: False
   vpn-disable-mode: off
   operational-mode: normal
   multi-vsys: off
   */
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "type=op&cmd=<show><system><info></info></system></show>", "result");
  $buffer = $sms_sd_ctx->get_raw_xml();

  $show_ver_asset_patterns = array(
      'serial' => '@<serial>(?<serial>\S+)</serial>@',
      'model' => '@<model>(?<model>[^<]+)</model>@',
      'firmware' => '@<sw-version>(?<firmware>[^<]+)</sw-version>@',
      'license' => '@<vm-license>(?<license>[^<]+)</vm-license>@',
      'ips_version' => '@<threat-version>(?<ips_version>[^<]+)</threat-version>@',
      'av_version' => '@<av-version>(?<av_version>[^<]+)</av-version>@',
      'as_version' => '@<threat-version>(?<as_version>[^<]+)</threat-version>@',
      'url_version' => '@<url-filtering-version>(?<url_version>[^<]+)</url-filtering-version>@'
  );

  // regular asset fields
  foreach ($show_ver_asset_patterns as $name => $pattern)
  {
    if (preg_match($pattern, $buffer, $matches) > 0)
    {
      $asset[$name] = trim($matches[$name]);
    }
  }

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "type=op&cmd=<request><license><info/></license></request>", "result");
  $buffer = $sms_sd_ctx->get_raw_xml();
  $xml = simplexml_load_string($buffer);
  $licenses = $xml->xpath("//response/result/licenses/entry");

  if (!empty($licenses))
  {
    echo "PA License info Fetch Succeed\n";
    $entries = $xml->xpath("//response/result/licenses/entry");

    $show_ver_license_patterns = array(
        'feature',
        'description',
        'serial',
        'issued',
        'expires',
        'expired',
        'base-license-name',
        'authcode'
    );

    $license_count = 1;
    foreach ($entries as $entry)
    {
      $feature = "";
      foreach ($show_ver_license_patterns as $name)
      {
        if (isset($entry->$name))
        {
          $node = trim(array_to_string($entry->xpath($name)));
          if ($name === "feature")
          {
            $feature = $node;
            $asset_key = "[License feature " . $license_count . "]";
          }
          else
          {
            $asset_key = "[" . $feature . "] " . $name;
            if ($name === "expires")
            {
              switch ($feature)
              {
                case "Threat Prevention":
		  $date = "00:00:00";
		  if (strpos($node, "Never") === false) {
			$date = date_conversion($node);
		  }
                  $asset['ips_expiration'] = $date;
                  $asset['av_expiration'] = $date;
                  $asset['as_expiration'] = $date;
                  break;
                case "PAN-DB URL Filtering":
                  $asset['url_expiration'] = $date;
                  break;
              }
            }
          }
          $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $asset_key, $node);
        }
      }
      $license_count++;
    }
  }
  else
  {
    echo "PA License info Fetch Failed\n";
  }

  /*****/
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  paloalto_generic_disconnect();
}

catch (Exception | Error $e)
{
  paloalto_generic_disconnect();
  debug_dump($asset, "Asset failed:\n");
  throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
}

return SMS_OK;

?>
