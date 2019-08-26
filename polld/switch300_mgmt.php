<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management

require_once 'smserror/sms_error.php';
require_once 'smsd/expect.php';
require_once 'smsd/sms_common.php';
require_once load_once('switch300', 'switch300_connection.php');

try
{
  // Connection
  $conn = new Switch300Connection();

  $asset = array();
  $asset_attributes = array(); //ce tableau contiendra tous les asset v2 (name => attribut)

  // model
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "sho cdp tlv");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "Platform TLV:");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^Platform TLV:\s*(.*)@', $line, $result) > 0)
    {
      $asset['model'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": Model [".$asset['model']."]\n";

      //EOS specific
      // extracting Product part number for model, PID in fact
      if (preg_match('@.*PID:(.*)\)@', $line, $result) > 0)
      {
        $asset_attributes['Product Part Number (PPN) 0'] = trim($result[1]);

      }
    }
  }

  // serial number
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show system id");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "Serial number");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^Serial\s*number\s*:\s*(.*)@', $line, $result) > 0)
    {
      $asset['serial'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": SerialNumber [".$asset['serial']."]\n";
    }
  }

  //CPU
  $regexCPU = '@is\s(.*)@';
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show cpu input rate");  //faire show cpu input rate
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regexCPU,$line,$matches) >0 )
    {
      $asset['cpu'] = trim($matches[1]);
    }

    $line = get_one_line($buffer);
  }

  // firmware
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show version");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "SW version");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^SW\s*version\s*(.*)@', $line, $result) > 0)
    {
      $asset['firmware'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": firmware [".$asset['firmware']."]\n";
    }
  }

  if ((sms_polld_set_asset_in_sd($sd_poll_elt, $asset)) !== 0)
  {
    throw SmsException("sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed", ERR_DB_FAILED);
  }

  //ASSET V2



  // System description
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show system");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "System Description");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@^System\s*Description:\s*(.*)@', $line, $result) > 0)
    {
      $asset_attributes['System description'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": model [".$asset['model']."]\n";
    }
  }


  //tableau qui contient les regexp :

  /*	$show_run_asset_attributes_patterns = array(
   '@^(?<value>\d*K bytes) of (?<name>[^\.]+)\.?\s$@',
  '@^(?<value>\d*) (?<name>.* interface)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* ip)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* line)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* port)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* Radio)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* service engine)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* Module)(s|\(s\))?\s$@',
  '@^(?<value>\d*) (?<name>.* \(SRE\))\s$@',
  '@with (?<value>\d*K/\d*K bytes) of (?<name>.*)\.@',
  '@^ROM: (?<name>System Bootstrap), Version (?<value>[^,]*),@'
  );

  //on lance un show run et pour chaque ligne on compare avec tous les regexp

  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show run");

  $line = get_one_line($buffer);
  while ($line !== false)
  {


  foreach ($show_run_asset_attributes_patterns as $pattern)
  {
  if (preg_match($pattern, $line, $matches) > 0)
  {
  $asset_attributes[trim($matches['name'])] = trim($matches['value']);
  }
  }

  $line = get_one_line($buffer);

  }
  */

  /**************************  ASSET ************************************/

  // Lister des interfaces et les rÃ©cuperer ligne par ligne grÃ¢ce Ã  une expression rÃ©guliÃ¨re
  $regexinterface = '@^(gi[0-9]{1,2}).*(Full|Half|--).*(100|10|--).*(Up|Down)@';
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show interfaces status");  //faire show int status
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regexinterface,$line,$matches) >0 )
    {
      $asset_attributes[trim($matches[1])] = trim($matches[4])." ".trim($matches[3])." ".trim($matches[2])." Duplex";
    }

    $line = get_one_line($buffer);
  }


  // Lister show power inline

  $regexpower = '@^\s+(gi[0-9]{1,2})\s(.*)@';
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show power inline");  //faire show power inline
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regexpower,$line,$matches) >0 )
    {
      $asset_attributes[trim($matches[1])] .= " / ".trim($matches[2]);
    }

    $line = get_one_line($buffer);
  }


  //Lister TCAM

  $regextcam = '@(TCAM utilization):(.*)@';
  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show system tcam utilization");  //faire show system tcam utilization
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regextcam,$line,$matches) >0 )
    {
      $asset_attributes[trim($matches[1])] .= trim($matches[2]);
    }

    $line = get_one_line($buffer);
  }

  // Tableau d'expressions rÃ©guliÃ¨re de show system
  $show_system_attributes_patterns = array(
	'@(System Up Time \(days,hour:min:sec\)):(.*)@',
	'@(System Contact):(.*)@',
	'@(System Name):(.*)@',
	'@(System Location):(.*)@',
	'@(System MAC Address):(.*)@',
	'@(System Object ID):(.*)@',
	'@(Fans Status):(.*)@'
  );

  $buffer = $conn->sendexpectone(__FILE__.':'.__LINE__, "show system");

  $line = get_one_line($buffer);
  while ($line !== false)
  {
    foreach ($show_system_attributes_patterns as $pattern)
    {
      if (preg_match($pattern, $line, $matches) > 0)
      {
        $asset_attributes[trim($matches[1])] = trim($matches[2]);
      }
    }

    $line = get_one_line($buffer);
  }


  foreach ($asset_attributes as $name => $value)
  {
    if (empty($value))
    {
      $value = "N/A";
    }

    $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $name, $value);
    if ($ret !== 0)
    {
      throw new SmsException(" sms_sd_set_asset_attribute($name, $value) Failed", ERR_DB_FAILED);
    }
  }
}
catch (Exception | Error $e)
{
  sms_log_error("Exception occur: " . $e->getMessage() . "\n");
  return $e->getCode();
}

return SMS_OK;

?>
