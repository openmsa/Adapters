<?php
 /*
  * Version: $Id$
  * Created: Jun 26, 2014
  * Available global variables
  *  $sms_sd_info   sd_info structure
  *  $sms_csp       pointer to csp context to send response to user
  *  $sdid          id of the device
  *  $optional_params	  optional parameters
  *  $sms_module    module name (for patterns)
  */

// Verb JSACMD ENROLL_CERTIFICATE
require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('cisco_isr', 'adaptor.php');

require_once "$db_objects";

// Required parameters
$req_params = array(
    'enrollment_url',
    'cert_CN_attribute', // CommonName // fqdn
    'cert_O_attribute',  // Organization
    'cert_OU_attribute', // OrganizationalUnit
    'cert_C_attribute',  // CountryName
    'ocsp_url'
    );

// Constants
$serialnumber=''; // could be 'none'
$rsakeypairlabel='RSA4096';

// We check that we have parameters
if (!isset($optional_params))
{
    $ret = ERR_VERB_BAD_PARAM;
    sms_send_user_error($sms_csp, $sdid, '["Missing params"]', $ret);
    return $ret;
}
$ret = SMS_OK;

// We decode parameters
$params = json_decode($optional_params, true);
debug_dump($params, 'PARAMS');

if (is_null($params))
{
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $error_msg = 'No error';
            break;
        case JSON_ERROR_DEPTH:
            $error_msg = 'Maximum depth reached';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $error_msg = 'Mismatch of modes or underflow';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $error_msg = 'Contains bad control chars';
            break;
        case JSON_ERROR_SYNTAX:
            $error_msg = 'JSON syntax error';
            break;
        case JSON_ERROR_UTF8:
            $error_msg = 'Contains wrong UTF-8 chars, probably an encoding issue';
            break;
        default:
            $error_msg = 'Unknown error';
        break;
    }
    $ret = ERR_VERB_BAD_PARAM;
    sms_log_error("$error_msg : $optional_params\n");
    sms_send_user_error($sms_csp, $sdid, "[\"$error_msg\":$optional_params]", $ret);
    return $ret;
}

// We check that we have all the requested parameters
foreach ($req_params as $req_param)
{
    if (empty($params[$req_param]))
    {
        $ret = ERR_VERB_BAD_PARAM;
        $error_msg = 'Missing parameter';
        sms_log_error("$error_msg : $req_param in $optional_params\n");
        sms_send_user_error($sms_csp, $sdid, "[\"$error_msg\":\"$req_param\", \"parameters\":$optional_params]", $ret);
        return $ret;
    }
}

// ====== Real work ============================================================
global $sms_sd_ctx;
$network = get_network_profile();
$SD = &$network->SD;

// Lock
$ret = sms_sd_lock($sms_csp, $sms_sd_info);
if ($ret !== 0)
{
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  sms_close_user_socket($sms_csp);
  return SMS_OK;
}

$ret = cisco_isr_connect();
if ($ret !== SMS_OK)
{
  sms_log_error(__FILE__.':'.__LINE__.": cisco_isr_connect() failed\n");
  sms_send_user_error($sms_csp, $sdid, "", $ret);
  return $ret;
}

$on_error_fct = 'cisco_isr_disconnect';
$result = '';

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'conf t', '(config)#');

// we remove possibly existing one, otherwise it will generate an error
unset($tab);
$tab[0] = "Can't find policy PKI";
$tab[1] = 'sure you want to do this? [yes/no]:';
$index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, 'no crypto pki trustpoint PKI', $tab);
if ($index == 1)
{
  sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'yes', '(config)#');
}

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, '!configure trustpoint/PKI end point', '(config)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'crypto pki trustpoint PKI', '(ca-trustpoint)#');

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'enrollment retry count 5', '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'enrollment mode ra', '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'enrollment url ' . $params['enrollment_url'], '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'usage ike', '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'serial-number ' . $serialnumber, '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'fqdn ' . $params['cert_CN_attribute'], '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'subject-name'
                                                            . ' CN=' . $params['cert_CN_attribute']
                                                            . ',O='  . $params['cert_O_attribute']
                                                            . ',OU=' . $params['cert_OU_attribute']
                                                            . ',C='  . $params['cert_C_attribute'], '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'subject-alt-name ' . $params['cert_CN_attribute'], '(ca-trustpoint)#');

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'revocation-check none', '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'ocsp url ' . $params['ocsp_url'], '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'rsakeypair ' . $rsakeypairlabel, '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'auto-enroll 90 regenerate', '(ca-trustpoint)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'hash sha256', '(ca-trustpoint)#');

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit', '(config)#');

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, '! Get CA Certificate', '(config)#');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'crypto ca authenticate PKI', 'accept this certificate? [yes/no]:');
sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'yes', '(config)#');

if (!empty($params['password']))
{
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, '!Device certificate enrolment', '(config)#');
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'crypto ca enroll PKI',          'Password:');
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $params['password'],             'Re-enter password:');
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $params['password'],             'IP address in the subject name? [no]:');
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'no',                            'Request certificate from CA? [yes/no]:');
    sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'yes',                           '(config)#');
}

sendexpectnobuffer(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'end', $sms_sd_ctx->getPrompt());

$cert_found = false;
$cert_buf = '';
if (!empty($params['password']))
{
    /* need to check if the certificate is received or not */
    $count = 18; // 3 minutes
    while ($count > 0)
    {
        /* retrieve the list of certificate in the router */
        $buf = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'show crypto pki certificates verbose PKI');

        $startpos = strpos($buf, 'Name: '.$params['cert_CN_attribute']);
        if ($startpos !== false)
        {
            $cert_found = true;
            $cert_buf = substr($buf, $startpos);
            // next Name:
            $endpos = strpos($cert_buf, 'Name: ', 1);

            if ($endpos !== false)
            {
                $cert_buf = substr_replace($cert_buf, '', $endpos);
            }
            break ;
        }
        sleep(10);
        $count--;
    }
}

if (! $cert_found || (strpos($cert_buf, 'Status: Available') === false))
{
    sms_log_error(" ENROLL_CERTIFICATE: Unable to find crypto ca authenticate Key");
    $ret = ERR_SD_CERTGEN;
}

unset($on_error_fct);
cisco_isr_disconnect();

$response = "[\"show_crypto_pki_certificate_log\":\"$buf\"]";

if ($ret !== SMS_OK)
{
  sms_send_user_error($sms_csp, $sdid, $response, $ret);
}
else
{
  sms_send_user_ok($sms_csp, $sdid, $response);
}
sms_sd_unlock($sms_csp, $sms_sd_info);
sms_close_user_socket($sms_csp);
return SMS_OK;

?>
