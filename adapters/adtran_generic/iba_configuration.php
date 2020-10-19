<?php
/*
 * Version: $Id: iba_configuration.php 32218 2010-06-28 11:59:03Z ees $
* Created: Mar 18, 2009
*/

// IOS Based Addon (IBA) card configuration generation
require_once 'smsd/sms_common.php';
require_once 'smsd/net_common.php';
require_once 'smsd/pattern.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/telnet_connection.php';

require_once load_once('adtran_generic', 'conf_parser.php');
require_once load_once('adtran_generic', 'common.php');
require_once load_once('adtran_generic', 'profile_configuration_interface.php');
require_once load_once('adtran_generic', 'apply_errors.php');

require_once "$db_objects";

/**
 * @addtogroup ciscoupdate
 * @{
 */


/**
 * IBA addon card configuration generation.
 */
class iba_configuration extends profile_configuration_interface
{
  var $voip;            // VoIP database profile
  var $sdid;            // ID of the SD to update
  var $sd;              // Current SD
  var $previous_conf;   // Saved previous generated configuration
  var $generated_conf;  // Generated configuration
  var $conf;            // Saved current generated configuration
  var $running_conf;    // Current configuration of the IBA
  var $conf_path;       // Path for previous stored configuration files
  var $conf_pflid;      // Configuration profile ID

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function __construct($sdid)
  {
    $this->name = 'IBA';
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    // Get data from database
    $this->sdid = $sdid;

    if (empty($this->sd))
    {
      $network = get_network_profile();
      $this->sd = &$network->SD;
      $this->conf_pflid = $network->SD->SD_CONFIGURATION_PFLID;
    }
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * generate the full config for delta
  */
  function get_conf(&$configuration)
  {

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate the general pre-configuration
  * @param $configuration   configuration buffer to fill
  */
  function get_pre_conf(&$configuration)
  {
    return get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'PRE_IBA_CONFIG', 'Configuration');
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate the general post-configuration
  * @param $configuration   configuration buffer to fill
  */
  function get_post_conf(&$configuration, $delta_conf = '')
  {
    return get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'POST_IBA_CONFIG', 'Configuration');
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Connect to the IBA addon via telnet from the SOC.
  * The ssh connection to the router must have been established first.
  */
  function connect_addon($clear_existing_connection = true)
  {
    global $sdid;
    global $sms_sd_ctx;
    global $sms_iba_ctx;
    global $buffer;

    // IBA addon card
    $interface = &$this->sd->SD_INTERFACE_list['A'];

    $sd_ip_addr = $this->sd->SD_IP_CONFIG;
    $login = $this->sd->SD_LOGIN_ENTRY;
    $passwd = $this->sd->SD_PASSWD_ENTRY;
    $adminpasswd = $this->sd->SD_PASSWD_ADM;
    $module_interface = "{$interface->INT_PHYSICAL_NAME}{$interface->INT_SUB_INTERFACE}";
    if (empty($module_interface))
    {
      $module_interface = 'wlan-ap0';
    }

    // Get telnet port
    $module_ready = false;
    for ($i = 1; ($i <= 30) && (!$module_ready); $i++)
    {
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, "service-module $module_interface status", '#');
      $line = strstr($buffer, 'via TTY line');
      if (preg_match('/via TTY line (?<number>\d+)/', $line, $matches) > 0)
      {
        $port_number = 2000 + $matches['number'];
      }
      else
      {
        sms_log_error(__FILE__.":$sdid:".__LINE__.": No TTY found to access the addon board $module_interface");
        sms_log_error(__FILE__.":$sdid:".__LINE__.": $buffer");
        return ERR_SD_ADDON_NETWORK;
      }
      $module_ready = true;
      break;
      sleep(10);
    }
    if (!$module_ready)
    {
      sms_log_error(__FILE__.":$sdid:".__LINE__.": addon board $module_interface not running");
      return ERR_SD_ADDON_NETWORK;
    }

    // clear current IBA connexion if any
    if ($clear_existing_connection)
    {
      unset($tab);
      $tab[0] = '[confirm]';
      $tab[1] = '#';
      $index = sendexpect(__FILE__.':'.__LINE__, $sms_sd_ctx, "service-module $module_interface session clear", $tab);
      switch ($index)
      {
        case 0:
          sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, '', '#');
          break;

        case 1:
          break;
      }

      sleep(2);
    }
    // Connect to IBA
    try{
    	$sms_iba_ctx = new CiscoIsrTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_number);
    	$sms_iba_ctx->setParam("PROTOCOL", "TELNET");
    }catch (SmsException $e) {
    	$login = "Cisco";
    	$passwd = "Cisco";
    	$adminpasswd = "Cisco";
    	sleep(5);
    	try{
    		$sms_iba_ctx = new CiscoIsrTelnetConnection($sd_ip_addr, $login, $passwd, $adminpasswd, $port_number);
    	}
    	catch (SmsException $e) {
    		return ERR_SD_CONNREFUSED;
    	}
    }
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function exit_addon()
  {
    global $sms_iba_ctx;

    if ($sms_iba_ctx != null)
    {
      $sms_iba_ctx->sendCmd(__FILE__.':'.__LINE__, "exit");
      sleep(1);
    }
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function init_iba()
  {
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function get_parser()
  {
    return get_parser_iba();
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get the configuration parser
  */
  function get_parser_clean()
  {
    return get_parser_iba();
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function is_active()
  {
    return !empty($this->sd->SD_INTERFACE_list['A']) && ($this->sd->SD_INTERFACE_list['A']->INT_NAME === 'IBA');
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate a full IBA configuration
  * Uses the running conf if specified or previous conf if present to perform deltas
  * @param $configuration   configuration buffer to fill
  * @param $use_running     if true use the running conf for the deltas
  */
  function generate(&$configuration, $use_running = false)
  {
    $configuration = '';

    $pre_conf = '';
    $this->get_pre_conf($pre_conf);
    $conf = '';
    $this->get_conf($conf);
    $post_conf = '';
    $this->get_post_conf($post_conf);

    // keep the conf in order to save it later
    $this->conf = $conf;

    $parser = $this->get_parser();
    $parsed_generated = parse_conf($conf, $parser);
    if ($use_running)
    {
      $parsed_previous = parse_conf($this->running_conf, $parser);
    }

    // Compare configurations
    $delta_conf = conf_differ($parsed_previous, $parsed_generated);

    // Generate the configuration from the delta
    $configuration .= "! IBA Configuration -- \n!\n";
    $configuration .= $pre_conf;
    $configuration .= generate_iba_conf_from_diff($delta_conf);
    $configuration .= $post_conf;
    $configuration .= "!\n! END IBA Configuration\n!\n";
    $this->generated_conf = $configuration;

    debug_dump($configuration, "IBA CONFIGURATION");

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Save current configuration to file
  */
  function save_generated()
  {
    save_result_file($this->conf, "$this->name.conf");
    save_result_file($this->generated_conf, "$this->name.gen");

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Load a previous generated configuration
  */
  function load_previous_generated()
  {
    $conf = '';
    $filename = "$this->conf_path/$this->sdid/$this->name.gen";
    if (file_exists($filename))
    {
      $handle = fopen($filename, 'r');
      if ($handle === false)
      {
        sms_log_error(__FILE__.':'.__LINE__.": fopen(\"$filename\") failed\n");
        return  ERR_LOCAL_FILE;
      }

      while (!feof($handle))
      {
        // PHP5 : $line = stream_get_line($orig, 10000, "\n");
        $line = fgets($handle);
        $conf .= $line;
      }
      fclose($handle);
    }
    return $conf;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get running configuration from the router
  */
  function get_running_conf()
  {
    global $sms_iba_ctx;

    $this->running_conf = sendexpectone(__FILE__.':'.__LINE__, $sms_iba_ctx, 'show run', $this->sd->ADDON_PROMPT);

    return $this->running_conf;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Save running configuration of the router
  */
  function save_running_conf()
  {
    $ret = $this->connect_iba();
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    $this->get_running_conf();

    $this->exit_iba();

    save_result_file($this->running_conf, "$this->name.running");

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Do backup IBA configuration
  */
  function do_backup_conf()
  {
    global $sms_iba_ctx;
    global $sms_ftp_ctx;
    $ftpserver_addr = $_SERVER['FTPSERVER_PUBIP'];
    $ftp_passwd = $this->sd->SD_FTP_PASSWD;
    $ftp_dir = "/opt/ftplogger/data/IOS/$this->sdid/IBAbackup";
    $ret = connect_ftp_cnx();
    if ($ret !== SMS_OK)
    {
      sms_log_error(__FILE__.':'.__LINE__.": Can't connect to ftplogger\n");
      return 0;
    }

    $cmd = "/opt/ftplogger/bin/ftp_update_account.sh I IOS $this->sdid $ftp_passwd";
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "$cmd; echo '[SMS:]:'");
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "mkdir -p $ftp_dir; echo '[SMS:]:'");
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "chown -R ftplogger:ftplogger $ftp_dir; echo '[SMS:]:'");

    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, 'conf t', '(config)#');
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, "backup server url ftp://$ftpserver_addr/IBAbackup username $this->sdid password $ftp_passwd", '(config)#');
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, "backup revisions 1", '(config)#');
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, "exit", $sms_iba_ctx->getPrompt());

    unset($tab);
    $tab[0] = "[n]?";
    $tab[1] = "[confirm]";
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_iba_ctx, "offline", $tab);

    if ($index === 0){
      sendexpectone(__FILE__.':'.__LINE__, $sms_iba_ctx, "y", "(offline)#" );
    }
    if ($index === 1){
      sendexpectone(__FILE__.':'.__LINE__, $sms_iba_ctx, "", "(offline)#" );
    }

    unset($tab);
    $tab[0] = 'Backup Complete';
    $tab[1] = 'Unable to connect to backup server';
    $tab[2] = 'Backup Failed';
    $index = sendexpect(__FILE__.':'.__LINE__, $sms_iba_ctx, 'backup category Configuration', $tab, 180000);

    if ($index === 1 || $index === 2){
      sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, "continue", $sms_iba_ctx->getPrompt());
      close_ftp_cnx();
      return ERR_SD_NETWORK;
    }

    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, "continue", $sms_iba_ctx->getPrompt());
    close_ftp_cnx();
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  function remove_backup_files()
  {
    global $sms_ftp_ctx;
    $ret = connect_ftp_cnx();
    if ($ret !== SMS_OK)
    {
      sms_log_error(__FILE__.':'.__LINE__.": Can't connect to ftplogger\n");
      return 0;
    }

    // Remove files to FTPLogger
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "rm -rf $this->FTPLOGGER_HOME/$this->iba_backup_path/Configuration_1/*; echo '[SMS:]:'");
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "rm -f $this->FTPLOGGER_HOME/$this->iba_backup_path/history.log; echo '[SMS:]:'");
    sendexpectone(__FILE__.':'.__LINE__, $sms_ftp_ctx, "rm -f $this->FTPLOGGER_HOME/$this->iba_backup_path.tar; echo '[SMS:]:'");
    close_ftp_cnx();

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Load a previous running configuration
  */
  function load_running_conf()
  {
    $this->running_conf = '';
    $filename = "$this->conf_path/$this->sdid/$this->name.running";
    if (file_exists($filename))
    {
      $handle = fopen($filename, 'r');
      if ($handle === false)
      {
        sms_log_error(__FILE__.':'.__LINE__.": fopen(\"$filename\") failed\n");
        return  ERR_LOCAL_FILE;
      }

      while (!feof($handle))
      {
        // PHP5 : $line = stream_get_line($orig, 10000, "\n");
        $line = fgets($handle);
        $this->running_conf .= $line;
      }
      fclose($handle);
    }
    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function apply_conf(&$configuration)
  {
    global $sms_iba_ctx;
    global $apply_iba_errors;

    if (strlen($configuration) === 0)
    {
      return SMS_OK;
    }

    save_result_file($configuration, "{$this->name}.applied");

    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, 'conf t', '(config)#');

    $line = get_one_line($configuration);
    while ($line !== false)
    {
      if (strpos($line, '!') === 0)
      {
        echo "$this->sdid: $line\n";
      }
      else
      {
        $SMS_OUTPUT_BUF .= sendexpectone(__FILE__.':'.__LINE__, $sms_iba_ctx, $line, '#');
      }
      $line = get_one_line($configuration);
    }
    save_result_file($SMS_OUTPUT_BUF, "{$this->name}.error");
    foreach ($apply_iba_errors as $apply_error)
    {
      if (preg_match($apply_error, $SMS_OUTPUT_BUF) > 0)
      {
        sms_log_error(__FILE__.':'.__LINE__.": [[!!! $SMS_OUTPUT_BUF !!!]]\n");
        return ERR_SD_CMDFAILED;
      }
    }


    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, 'end', '#');
    sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_iba_ctx, 'write mem', '#');

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function update_conf($configuration = '')
  {

    if (!$this->is_active())
    {
      return SMS_OK;
    }

    $ret = $this->connect_iba();
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    if (empty($configuration))
    {
      if (empty($this->running_conf))
      {
        $this->get_running_conf();
      }

      $this->generate($configuration, true);
    }

    $this->apply_conf($configuration);

    $this->exit_iba();

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function provisioning()
  {
    if (!$this->is_active())
    {
      return SMS_OK;
    }

    $ret = $this->connect_iba();
    if ($ret != SMS_OK)
    {
      return $ret;
    }

    $this->init_iba();

    //    $configuration = $this->get_running_conf();

    $this->generate($configuration, true);

    $this->apply_conf($configuration);

    $this->exit_iba();

    return SMS_OK;
  }
}

/**
 * @}
 */

?>