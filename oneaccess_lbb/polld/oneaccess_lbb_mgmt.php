<?php
/*
 * Available global variables
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('oneaccess_lbb', 'oneaccess_lbb_connection.php');


try
{
  // Connection
  $conn_status = oneaccess_lbb_connect();
  if($conn_status != SMS_OK){
  	return ERR_SD_CONNREFUSED;
  }
  $conn = $sms_sd_ctx;
  $asset = array();

  // serial number
  $buffer = $conn->sendexpectone(__LINE__, "show system status");
  echo "buffer: $buffer \n";

  $line = strpos($buffer, "S/N");
  if ($line !== false)
  {
    if (preg_match('@(S/N)\s(.*)@', $line, $result) > 0)
    {
      $asset['serial'] = trim($result[2]);
      echo __FILE__.':'.__LINE__.": SerialNumber [".$asset['serial']."]\n";
    }
  }

  //Licence
  $asset['license'] = "N/A";

  //CPU
  $regexCPU = '@CPU.(.*):.(.*)@';
  $buffer = $conn->sendexpectone(__LINE__, "show system hardware");  //faire show system hardware
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regexCPU,$line,$matches) >0 )
    {
      $asset['cpu'] = trim($matches[2]);
    }

    $line = get_one_line($buffer);
  }

  //Mémoire
  $regexCPU = '@Ram size :  ([0-9]+Mo)@';
  $buffer = $conn->sendexpectone(__LINE__, "show system hardware");  //faire show system hardware
  $line = get_one_line($buffer);                                       // on prend 1 ligne du show

  while ($line !== false)
  {
    if (preg_match($regexCPU,$line,$matches) >0 )
    {
      $asset['memory'] = trim($matches[1]);
    }

    $line = get_one_line($buffer);
  }

  // firmware
  $buffer = $conn->sendexpectone(__LINE__, "show version");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "Software version");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@Software version    :(.*)@', $line, $result) > 0)
    {
      $asset['firmware'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": firmware [".$asset['firmware']."]\n";
    }
  }

  //model
  $buffer = $conn->sendexpectone(__LINE__, "show system hardware");
  printf("buffer: $buffer \n");

  $line = strstr($buffer, "Device");
  printf("line: $line \n");
  if ($line !== false)
  {
    if (preg_match('@Device   :\s(.*)@', $line, $result) > 0)
    {
      $asset['model'] = trim($result[1]);
      echo __FILE__.':'.__LINE__.": model [".$asset['model']."]\n";
    }
  }

  if ((sms_polld_set_asset_in_sd($sd_poll_elt, $asset)) !== 0)
  {
    throw SmsException("sms_polld_set_asset_in_sd($sd_poll_elt, $asset) Failed", ERR_DB_FAILED);
  }

  //ASSET V2


  $asset_attributes = array(); //ce tableau contiendra tous les asset v2 (name => attribut)

  // Tableau d'expressions régulière de show run
  $show_system_attributes_patterns = array(
	'@^(Bvi.[0-9])\sis(.up|down)@',
	'@(FastEthernet [0-9]/[0-9])\sis\s(up|down)@',
	'@(Loopback [0-9])\sis(.up|down)@'
	);

	$buffer = $conn->sendexpectone(__LINE__, "show ip interface");

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