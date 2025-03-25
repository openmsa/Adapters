<?php
/*
 * Version: $Id$
 * Created: Jun 18, 2015
 * Available global variables
 *  $sms_sd_info      sd_info structure
 *  $sms_csp          pointer to csp context to send response to user
 *  $sdid             id of the device
 *  $optional_params  optional parameters (<vdom-name> <cert-content-b64> )
 *  $sms_module       module name (for patterns)
 */

// Verb JSACMD LOCAL_CERT_IMPORT
// inter_ca_import root certificatb64content
// sms -e JSACMD -i "NTT1141 local_cert_import" -c "root certificatefilename keyfilename certificate_base64_content key_base64_content password "

$router_kind='fortinet_generic';

require_once 'smsd/sms_common.php';
require_once load_once($router_kind, $router_kind . '_connect.php');
require_once load_once($router_kind, $router_kind . '_configuration.php');
require_once "$db_objects";


$vdom = 'root';
$certfilename = '';
$keyfilename = '';
$certpem = '';
$keypem = '';
$keypass = '';

$conf = new fortinet_generic_configuration($sdid);
$datacenter_ip = $conf->get_additional_vars('DATACENTER_IP');


interface param_state
{
  const vdom = 0;
  const certfilename = 1;
  const keyfilename = 2;
  const certpem = 3;
  const keypem = 4;
  const keypass = 5;
  const nomore = 6;
}

$state = param_state::vdom;
$params = preg_split('/\r\n/', $optional_params);
/*
foreach ($params as $param)
{
  echo "param found: <" . $param . ">\n";
}
*/
foreach ($params as $param)
{
//  echo "current state: " . $state . "\n";

  switch($state)
  {
    case param_state::vdom:
      $vdom = $param;
      $state = param_state::certfilename;
      break;
    case param_state::certfilename:
      $certfilename = $param;
      $state = param_state::keyfilename;
      break;
    case param_state::keyfilename:
      $keyfilename = $param;
      $state = param_state::certpem;
      break;
    case param_state::certpem:
      if(strpos($param, 'END_CERTIFICATE') !== FALSE)
      {
        $state = param_state::keypem;
        $certpem = base64_decode($certpem);
      } else {
      	$certpem = $certpem . "\n" . $param;
      }
      break;
    case param_state::keypem:
      if(strpos($param, 'END_KEY') !== FALSE)
      {
        $state = param_state::keypass;
        $keypem = base64_decode($keypem);
      } else {
      	$keypem = $keypem . "\n" . $param;
      }
      break;
    case param_state::keypass:
      $keypass = $param;
      $state = param_state::nomore;
      break;
    case param_state::nomore:
      break;
    default:
      echo "unexpected step. param: " . $param . "\n";
      break;
  }
}
/*
echo "vdom: " . $vdom . "\n";
echo "certfilename: <" . $certfilename . ">\n";
echo "keyfilename: <" . $keyfilename . ">\n";
echo "certpem: " . $certpem . "\n";
echo "keypem: " . $keypem . "\n";
echo "keypass: " . $keypass . "\n";
*/
$status_type = 'CERT_UPLOAD';

$net_profile = get_network_profile();
$sd = &$net_profile->SD;

$ret = fortinet_generic_connect();
if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_log_error(__FILE__.':'.__LINE__.": fortinet_generic_connect() failed\n");
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

$on_error_fct = 'fortinet_generic_exit';

$conf = new fortinet_generic_configuration($sdid);

$status_message = "";

$tftp_Server_Address = $datacenter_ip;


$tmp_dir = $sdid . "_" . rand(100000, 999999);
$tftp_dir = $_SERVER['TFTP_BASE'] . "/" . $tmp_dir;
mkdir($tftp_dir, 0755);

$certfilePath = "/opt/sms/spool/tftp/" . $tmp_dir . "/" . $certfilename;
echo "create the file from b64 parameter and put it on TFTP: " . $certfilePath . "\n";
$cert_file = fopen($certfilePath, "w");
fwrite($cert_file, $certpem);
fclose($cert_file);
$keyfilePath = "/opt/sms/spool/tftp/" . $tmp_dir . "/" . $keyfilename;
echo "create the file from b64 parameter and put it on TFTP: " . $keyfilePath . "\n";
$key_file = fopen($keyfilePath, "w");
fwrite($key_file, $keypem);
fclose($key_file);


$ret = func_local_cert_import($sdid, $sms_csp, 'root', $certfilename, $keyfilename, $keypass, $tmp_dir, $tftp_Server_Address);

rmdir_recursive($tftp_dir);

fortinet_generic_disconnect(true);

sms_close_user_socket($sms_csp);

return $ret;



function fortinet_generic_exit()
{
  fortinet_generic_disconnect(true);
}


function func_local_cert_import($sdid, $sms_csp, $vdom, $certpath, $keyname, $password, $tmp_dir, $tftp_Server_Address)
{
  global $sms_sd_ctx;
  global $sendexpect_result;
  global $result;
  $status_type = 'CERT_UPLOAD';
  
  echo "prompt: " . $sms_sd_ctx->getPrompt() . "\n";

  $end = false;
  $tab[0] = 'Cannot reading certificate file';
  $tab[1] = 'File not found';
  $tab[2] = 'Certificate already exists';
  $tab[3] = 'import local certificate failed';
  $tab[4] = 'Command fail';
  $tab[5] = 'file is not a certificate';
  $tab[6] = $sms_sd_ctx->getPrompt(); // 'todoGet config file from tftp server OK.';

  $cmd_line = 'execute certificate local cert import tftp ' . $vdom . ' ' . $tmp_dir . '/' . $certpath . ' ' . $tmp_dir . '/' . $keyname . ' ' . $tftp_Server_Address . ' ' . $password;
  do
  {
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
    switch($index)
    {
      case 0:
      	$msg="Cannot read certificate file";
        sms_send_user_error($sms_csp, $sdid, $msg, ERR_SD_CERT_UPLOAD_CANNOTREAD_CERT);
        sms_log_error(__FILE__.':'.__LINE__.":".$msg."\n");
        sms_set_update_status($sms_csp, $sdid, ERR_SD_CERT_UPLOAD_CANNOTREAD_CERT, $status_type, 'FAILED', '', $msg);
        $end = true;
        return ERR_SD_CERT_UPLOAD_CANNOTREAD_CERT;
        break;
      case 1:
      	$msg="file not found on tftp server";
        sms_send_user_error($sms_csp, $sdid, $msg, ERR_SD_CERT_UPLOAD_FILENOTFOUND);
        sms_log_error(__FILE__.':'.__LINE__.":".$msg."\n");
        sms_set_update_status($sms_csp, $sdid, ERR_SD_CERT_UPLOAD_FILENOTFOUND, $status_type, 'FAILED', '', $msg);
        $end = true;
        return ERR_SD_CERT_UPLOAD_FILENOTFOUND;
        break;
      case 2:
      	$msg="Certificate already exists";
        sms_send_user_error($sms_csp, $sdid, $msg, ERR_SD_CERT_UPLOAD_CERTEXIST);
        sms_log_error(__FILE__.':'.__LINE__.":".$msg."\n");
        sms_set_update_status($sms_csp, $sdid, ERR_SD_CERT_UPLOAD_CERTEXIST, $status_type, 'FAILED', '', $msg);
      	$end = true;
        return ERR_SD_CERT_UPLOAD_CERTEXIST;
        break;
      case 3:
      	$msg="import local certificate failed";
        sms_send_user_error($sms_csp, $sdid, $msg, ERR_SD_CERT_IMPORT_FAIL);
        sms_log_error(__FILE__.':'.__LINE__.":".$msg."\n");
        sms_set_update_status($sms_csp, $sdid, ERR_SD_CERT_IMPORT_FAIL, $status_type, 'FAILED', '', $msg);
      	$end = true;
        return ERR_SD_CERT_IMPORT_FAIL;
        break;
      case 4:
      	$msg="Command fail. CLI parsing error.";
      	sms_send_user_error($sms_csp, $sdid, $msg, ERR_SD_CMDFAILED);
       	sms_log_error(__FILE__.':'.__LINE__.":".$msg."\n");
       	sms_set_update_status($sms_csp, $sdid, ERR_SD_CMDFAILED, $status_type, 'FAILED', '', $msg);
       	$end = true;
       	return ERR_SD_CMDFAILED;
       	break;
      case 5:
        $end = true;
        sms_send_user_ok($sms_csp, $sdid,"");
        break;
      default:
        echo "Unmanaged error\n";
        return ERR_SD_FAILED;
    }
  } while ($end !== true);

  return SMS_OK;
}

?>

