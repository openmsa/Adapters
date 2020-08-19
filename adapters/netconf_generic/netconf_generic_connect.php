<?php
/*
 * Version: $Id$
 * Created: Feb 12, 2015
 * Available global variables
 */

// Manage Netconf over SSH connections
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once 'smsd/ssh_connection.php';
require_once 'smsd/expect.php';
require_once 'smsd/generic_connection.php';

require_once "$db_objects";
class NetconfGenericsshConnection extends GenericConnection
{
  private $SMS_OUTPUT_BUF;
  private $last_rpc_reply;
  private $hello_rpc;
  private $defaultcapabilities;
  private $raw_xml;
  private $device_capabilities;
  public function do_connect()
  {
    global $sendexpect_result;
    $cnx_timeout = 10;
    $reply_timeout = 600; // reply_timeout rfc6241
    global $sms_sd_ctx;
    $delay = EXPECT_DELAY / 1000;

    try
    {
      parent::connect("ssh -s -p {$this->sd_management_port} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}' netconf");
    }
    catch (SmsException $e)
    {
      // try the alternate port
      if (!empty($this->sd_management_port_fallback) && ($this->sd_management_port !== $this->sd_management_port_fallback))
      {
        parent::connect("ssh -s -p {$this->sd_management_port_fallback} -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o PreferredAuthentications=password -o NumberOfPasswordPrompts=1 '{$this->sd_login_entry}@{$this->sd_ip_config}' netconf");
      }
    }

    unset($tab);
    $tab[0] = 'assword:';
    $tab[1] = 'passphrase';
    $tab[2] = '(yes/no)?';
    $tab[3] = "]]>]]>";
    $tab[4] = 'added';
    try
    {
      $this->expect(__FILE__.':'.__LINE__, $tab, $cnx_timeout * 1000);
    }
    catch (SmsException $e)
    {
      throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
    }

    $this->do_store_prompt();

    $index = 0;
    foreach ($tab as $t)
    {
      if (strpos($sendexpect_result, $t) !== false){
        break;
      }
      $index++;
    }
    if ($index > 4)
    {
      $index = $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    if ($index == 0 || $index == 1)
    {
      $this->sendCmd(__FILE__.':'.__LINE__, $this->sd_passwd_entry);
      $this->expect(__FILE__.':'.__LINE__, $tab);

      $this->sendCmd(__FILE__.':'.__LINE__, create_hello_rpc(default_hellocapabilities_rpc()));
      $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    if ($index == 2)
    {
      try
      {
        $this->sendCmd(__FILE__.':'.__LINE__, "yes", $prompt);
      }
      catch (Exception | Error $e)
      {
        throw new SmsException("{$this->connectString} Failed", ERR_SD_CONNREFUSED);
      }

      $this->sendCmd(__FILE__.':'.__LINE__, create_hello_rpc(default_hellocapabilities_rpc()));
      $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    if ($index == 4)
    {
			$this->sendCmd(__FILE__.':'.__LINE__, create_hello_rpc(default_hellocapabilities_rpc()));
      $this->expect(__FILE__.':'.__LINE__, $tab);
    }

    $this->setParam('suppress_echo', true);
    $this->setParam('suppress_prompt', true);
  }

  function do_store_prompt()
  {
    $this->prompt = ']]>]]>';
  }

  public function get_raw_xml()
  {
    return $this->raw_xml;
  }

  public function get_device_capabilities()
  {
    echo "Devices capabilities: " . $this->device_capabilities . "\n";
    return $this->device_capabilities;
  }

  public function sendexpectone($origin, $cmd, $prompt = 'lire dans sdctx', $delay = EXPECT_DELAY, $display_error = true)
  {
    if ($prompt == 'lire dans sdctx')
    {
      $prompt = $this->prompt;
      if (is_null($prompt))
      {
        throw new SmsException("no prompt defined for {$this->connectString}", ERR_LOCAL_PHP, $origin);
      }
    }

    // Send ]]>]]> at the end of the command
    $cmd = str_replace("\r", '', str_replace(" \r", '', str_replace("\r\n", "\n", $cmd)));
    $this->sendCmd($origin, "{$cmd}{$this->prompt}");

    unset($tab);
    $tab[0] = $prompt;
    $this->expect($origin, $tab, $delay, $display_error);

    return $this->last_result;
  }
}

// ----------------------------------------------------------------------------------------
function netconf_generic_connect($sd_ip_addr = null, $login = null, $passwd = null, $port_to_use = null)
{
  global $sms_sd_ctx;

  $sms_sd_ctx = new NetconfGenericsshConnection($sd_ip_addr, $login, $passwd, $port_to_use);

  return SMS_OK;
}

// ----------------------------------------------------------------------------------------
function create_hello_rpc(array $capabilities)
{
  $hello_rpc = "\n<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
  $hello_rpc .= "<hello xmlns=\"urn:ietf:params:xml:ns:netconf:base:1.0\">\n";
  $hello_rpc .= "<capabilities>\n";
  foreach ($capabilities as $cap)
  {
    $hello_rpc .= "<capability>" . $cap . "</capability>\n";
  }
  $hello_rpc .= "</capabilities>\n";
  $hello_rpc .= "</hello>]]>]]>";
  return $hello_rpc;
}

// ----------------------------------------------------------------------------------------
function default_hellocapabilities_rpc()
{
  unset($defaultcapabilities);
  $defaultcapabilities[] = "urn:ietf:params:netconf:base:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:candidate:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:confirmed-commit:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:xpath:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:url:1.0?scheme=ftp,sftp,file";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:validate:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:rollback-on-error:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:notification:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:interleave:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:partial-lock:1.0";
  $defaultcapabilities[] = "urn:ietf:params:netconf:capability:with-defaults:1.0?basic-mode=trim&amp;also-supported=report-all-tagged";
  return $defaultcapabilities;
}

// ----------------------------------------------------------------------------------------
// as string and get string
function get_rpc_reply($rpc)
{
  global $sms_sd_ctx;
  $SMS_OUTPUT_BUF = '';

  $rpc_reply = '';
  fwrite_stream($SMS_OUTPUT_BUF, $rpc . "/n");
  sms_log_debug(__LINE__ . ':' . __FILE__, fwrite_stream($SMS_OUTPUT_BUF, $rpc . "/n"));
  while (1)
  {
    $line = fgets($SMS_OUTPUT_BUF);
    if (strncmp($line, "<rpc ", 5) == 0)
    {
      if (strpos($line, "]]>]]>"))
      {
        continue;
      }
      else
      {
        while (1)
        {
          $line = fgets($SMS_OUTPUT_BUF);
          if (strpos($line, "]]>]]>"))
          {
            $line = fgets($SMS_OUTPUT_BUF);
            break;
          }
        }
      }
    }
    if ((strncmp($line, "]]>]]>", 6)) == 0)
    {
      break;
    }
    $rpc_reply .= $line;
  }
  return $rpc_reply;
}

// ----------------------------------------------------------------------------------------
// rpc will be sent as a XML or String and the reply one will be in XML format
function send_rpc($rpc)
{
  if ($rpc == null)
    throw new SmsException("");
  if (gettype($rpc) == "string")
  {
    if (!startsWith($rpc, "<rpc "))
    {
      $rpc .= "<rpc xmlns=\"urn:ietf:params:xml:ns:netconf:base:1.0\" message-id=\"101\"><" . $rpc . "></rpc>";
      $rpc .= "]]>]]>";
    }
    $rpc_reply_string = get_rpc_reply($rpc);
  }
  else
  {
    $rpcstring = strval($rpc);
    debug_dump($rpcstring);
    $rpc_reply_string = get_rpc_reply($rpcstring);
  }
  $this->last_rpc_reply = $rpc_reply_string;
  $rpc_reply_xml = new SimpleXMLElement($rpc_reply_string);
  $raw_xml = $rpc_reply_xml->asXML();
  $rpc_reply = $raw_xml;
  return $rpc_reply;
}

// ----------------------------------------------------------------------------------------
function getLast_rpc_reply()
{
  return $this->last_rpc_reply;
}

// ----------------------------------------------------------------------------------------
function lock_config()
{
  $rpc = "<rpc xmlns=\"urn:ietf:params:xml:ns:netconf:base:1.0\" message-id=\"101\">";
  $rpc .= "<lock>";
  $rpc .= "<target>";
  $rpc .= "<candidate/>";
  $rpc .= "</target>";
  $rpc .= "</lock>";
  $rpc .= "</rpc>";
  $rpc .= "]]>]]>\n";
  $rpc_reply = $this->get_rpc_reply($rpc);
  $this->last_rpc_reply = $rpc_reply;
  /*
   * traiter le cas d'erreur si on arrive pas à locker la configuration
   * necessité de traiter les erreur de rpc-reply
   */
}

// ----------------------------------------------------------------------------------------
function close_session()
{
  $rpc = "<rpc xmlns=\"urn:ietf:params:xml:ns:netconf:base:1.0\" message-id=\"101\">";
  $rpc .= "<close-session/>";
  $rpc .= "</rpc>";
  $rpc .= "]]>]]>\n";
  $rpc_reply = $this->get_rpc_reply($rpc);
  $this->last_rpc_reply = $rpc_reply;
  fclose($this->SMS_OUTPUT_BUF);
}

// ----------------------------------------------------------------------------------------
function startsWith($haystack, $needle)
{
  $length = strlen($needle);
  return (substr($haystack, 0, $length) === $needle);
}

// Disconnect
function netconf_generic_disconnect()
{
  global $sms_sd_ctx;
  $sms_sd_ctx = null;
  return SMS_OK;
}

?>

