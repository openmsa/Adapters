<?php
/*
 * Version: $Id: netasq_configuration.php 24140 2009-11-24 14:46:35Z tmt $
 * Created: Feb 12, 2009
 */
require_once 'smsd/sms_common.php';
require_once 'smsd/net_common.php';
require_once 'smsd/pattern.php';

require_once load_once('stormshield', 'nsrpc.php');
require_once load_once('stormshield', 'netasq_connect.php');
require_once load_once('stormshield', 'smarty_functions.php');

require_once "$db_objects";


/**
 * @addtogroup ciscoupdate  Cisco Routers Update Process
 * @{
 */

/** Main configuration manager
 * All the profile managed are listed in the constructor.
 */
class netasq_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $partner_sdid; // ID of the HA partner
  var $sd; // current SD
  var $partner_sd; // HA partner of the current SD
  var $cli_prefix; // cli prefix (trigram operator) of the SD to update
  var $abonne; // abonne of the SD.
  var $pflid; // Profil ID of the SD to update
  var $conf_applied_tree; // Current generated configuration waiting to be saved
  var $conf_error; // Error when applying configuration
  var $version; // version of the device
  var $model; // model of the device
  var $serial_number; // serial number of the device
  var $scripts; // scripts associated
  var $license; // license associated
  var $firmware; // firmware associated
  var $fmc_repo; // repository path without trailing /
  var $fmc_ent; // entities path without trailing /
  var $validate_passwd; // password used for CONFIG VALIDATE STATUS and CONFIG STATUS CHECK
  var $ncm_ip_addr; // ip addr of NCM
  var $log_level; // level of the log to store in DB (for level <= 1 an alarm is generated)
  var $log_ref; // reference of the log to store in DB
  var $log_msg; // message of the log  to store in DB
  var $spool_folder; // folder where to generate the conf
  var $event_rules_present; // boolean indicates whether Event/rules file is present for provisioning only
  var $thread_id; // unique identifier

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  * The list of all the managed profiles is created here.
  * @param $sdid    SD ID of the current SD to configure
  * @param $is_provisionning    true if the update is for provisioning (more initializations)
  */
  function __construct($sdid)
  {

    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    // Network profile
    $net_conf = get_network_profile();
    $this->sd = & $net_conf->SD;
    $this->pflid = $this->sd->SD_CONFIGURATION_PFLID;
    $this->cli_prefix = $this->sd->SD_CLI_PREFIX;
    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      $this->partner_sd = & $net_conf->partner_SD;
      $this->partner_sdid = $this->partner_sd->SDID;
    }
    else
    {
      $this->partner_sd = null;
      $this->partner_sdid = "";
    }
    $this->abonne = $this->sd->SD_ABONNE;
    $this->conf_error = '';
    $this->conf_applied_tree = '';
    $this->scripts = array ();
    $this->license = array ();
    $this->firmware = array();
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $this->fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];
    $this->ncm_ip_addr = $_SERVER['SMS_ADDRESS_IP'];
    $this->validate_passwd = sha1("UBIqube-$sdid");
    $this->event_rules_present = false;
    $this->thread_id = $_SERVER['THREAD_ID'];
  }

  function __destruct()
  {
    rmdir_recursive("/opt/sms/spool/tmp/cert_{$this->thread_id}");
  }

  function get_cli_prefix()
  {
    return $this->cli_prefix;
  }

  function get_abo()
  {
    return $this->abonne;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Generate the Netasq configuration tree
  * @param $pflid  ID of the configuration profile
  * @param $folder folder where to put files
  */
  function generate($pflid)
  {
    global $resolve_template_error;
    $prefix_path = '/usr/Firewall/ConfigFiles';

    $sdid = $this->sdid;
    $folder = $this->spool_folder;

    echo "Entering in function generate( -$sdid-, -$pflid-, -$folder- )\n";

    $map_conf = array ();

    $ret = get_map_from_xml("$this->fmc_ent/$pflid.xml", $map_conf, $this->conf_error, 'Configuration');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $ret = get_map_from_xml("$this->fmc_ent/$sdid.xml", $map_conf, $this->conf_error, 'Configuration');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($map_conf as $mvalue)
    {
      $start = strpos($mvalue, $prefix_path);
      if ($start === false)
      {
        continue;
      }

      $file_path = substr($mvalue, $start);
      $copy_from = "$this->fmc_repo/$mvalue";
      $copy_to = "$folder$file_path";
      echo "copying from $copy_from to $copy_to\n";

      // Create dir path
      $dir_path = dirname($copy_to);
      if ($dir_path !== '.')
      {
        mkdir_recursive($dir_path, 0755);
      }

      // only text file type have to be resolved by smarty
      $ftype = $finfo->file($copy_from);
      if (($ftype === false) || (strpos($ftype, 'text') === false))
      {
        $config = file_get_contents($copy_from);
      }
      else
      {
        $config = resolve_template($sdid, $copy_from);

        if (!empty($resolve_template_error))
        {
          if (strpos($resolve_template_error, 'Trying to get property of non-object') !== false)
          {
            // At least one undefined variable in a template, stop the generation
            return ERR_CONFIG_VAR_UNDEFINED;
          }
        }
      }

      $ret = file_put_contents($copy_to, $config);
      if ($ret === false)
      {
      	sms_log_error(__FILE__.':'.__LINE__.": file_put_contents(\"$copy_to\", \"$data\") failed\n");
        return ERR_LOCAL_FILE;
      }

      if (($this->event_rules_present === false) && (strpos($mvalue, "Event/rules") !== false))
      {
        $this->event_rules_present = true;
      }
    }

    $this->conf_applied_tree = $folder;

    // Read associated scripts
    $ret = get_map_from_xml("{$this->fmc_ent}/$sdid.xml", $this->scripts, $this->conf_error, 'Script');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }

  /**
   * Create a netasq na archive ($na_archive) with files contained in $folder
   * tgz must have absolute path, i.e /usr/...
   * @param $folder		source directory containing file to archive
   * @param $na_archive	destination .na file (containing files)
   */
  function create_na_archive($folder, $na_archive, $na_archive_file_name)
  {

    if (!file_exists("$folder/usr"))
    {
      // empty config, nothing to do
      return SMS_OK;
    }

    $tgz = "$na_archive.tgz";
    $tgz_file_name = "$na_archive_file_name.tgz";

    echo "Create tar file ($tgz)\n";
    // Prepare chroot environement
    $lib = 'lib64';
    $libraries = '/lib64/libselinux.so.1 /lib64/libacl.so.1 /lib64/librt.so.1 /lib64/libc.so.6 /lib64/libdl.so.2 /lib64/ld-linux-x86-64.so.2 /lib64/libattr.so.1 /lib64/libpthread.so.0 /lib64/libm.so.6';

    $ret = exec_local(__FILE__ . ':' . __LINE__, "mkdir $folder/bin $folder/$lib 2>&1 && cp /bin/tar /bin/gzip /bin/chmod /bin/chown /usr/bin/find $folder/bin 2>&1 && cp $libraries $folder/$lib 2>&1", $output);
    if ($ret !== SMS_OK)
    {
      exec_local(__FILE__ . ':' . __LINE__, "rm -rf $folder/bin $folder/$lib 2>&1", $output);
      return $ret;
    }
    exec_local(__FILE__ . ':' . __LINE__, "sudo /usr/sbin/chroot $folder /bin/find /usr -type f -exec chmod 644 {} \; 2>&1", $output);
    exec_local(__FILE__ . ':' . __LINE__, "sudo /usr/sbin/chroot $folder /bin/find /usr -type d -exec chmod 755 {} \; 2>&1", $output);
    $ret = exec_local(__FILE__ . ':' . __LINE__, "sudo /usr/sbin/chroot $folder /bin/tar czPf /$tgz_file_name /usr 2>&1", $output);
    exec_local(__FILE__ . ':' . __LINE__, "rm -rf $folder/bin $folder/$lib 2>&1", $output);
    if ($ret !== SMS_OK)
    {
      $ret2 = exec_local(__FILE__ . ':' . __LINE__, "cd $folder 2>&1 && ls $tgz > /dev/null 2>&1 ; echo $?", $output);

      if (isset($output[0]) && $output[0] !== '0')
      {
        return ERR_CONFIG_EMPTY;
      }
      return $ret;
    }

    echo "Create the .na file ($na_archive)\n";
    $ret = exec_local(__FILE__ . ':' . __LINE__, "cd /opt/sms/bin/netasq 2>&1 && ./encbackup -i $tgz -o $na_archive -t all -v $this->version -m $this->model -s $this->serial_number -r netasq.ca -e encbackup.cert -k encbackup.key 2>&1", $output);
    $ret2 = exec_local(__FILE__ . ':' . __LINE__, "rm -f $tgz 2>&1", $output);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    if ($ret2 !== SMS_OK)
    {
      return $ret2;
    }

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Save current configuration to file
  */
  function save_generated()
  {

    $filepath = "$this->conf_path/$this->sdid";
    mkdir_recursive($filepath, 0755);

    $filename = "$filepath/conf.error";
    $handle = fopen($filename, "w");
    if ($handle === false)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": fopen(\"$filename\") failed\n");
      return ERR_LOCAL_FILE;
    }

    foreach ($this->conf_error as $line)
    {
      $ret = fputs($handle, "$line\n");
      if ($ret === false)
      {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": fputs(\"$filename\", \"$line\") failed\n");
        fclose($handle);
        unlink($filename);
        return ERR_LOCAL_FILE;
      }
    }

    fclose($handle);

    $ret = exec_local(__FILE__ . ':' . __LINE__, "rm -f $this->conf_path/$this->sdid/tree.applied.tosave && ln -s $this->conf_applied_tree $this->conf_path/$this->sdid/tree.applied.tosave 2>&1", $output);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }

  /**
   * Decode data (b64) sent by a device in response to a command
   * @param $array_lines
   */
  function decode_data_from_device($array_lines)
  {
    $conf = '';

    $data_started = false;

    foreach ($array_lines as $i => $value)
    {
      $value = trim($value);

      //echo "Line read: $value\n";

      if ($value === 'Saving to:')
      {
        $data_started = true;
      }
      else
      {
        if ($data_started === true)
        {
          $pos_code = strpos($value, 'code=');
          if ($pos_code !== false)
            break;
          //$conf .= "------------------------------\n";
          $conf .= base64_decode($value);
          //$conf .= "\n------------------------------\n";
        }
      }
    }

    return $conf;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Send a command, get the response in base64 from the router and decode
  */
  function send_expect_b64($cmd, $prompt)
  {
    global $sms_sd_ctx;

    $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, $prompt);

    $array_lines = array ();
    $line = get_one_line($buffer);
    while ($line !== false)
    {
      //$array_lines[] = $line;
      array_push($array_lines, $line);
      $line = get_one_line($buffer);
    }

    $buffer = $this->decode_data_from_device($array_lines);
    return $buffer;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get running configuration from the router
  */
  function get_running_conf($archive_file)
  {
    echo "Entering get_running_conf($archive_file)\n";

    $n = new nsrpc($this->sd);

    echo "nsrpc created\n";

    $n->add_action("modify on force");
    $n->add_action("config backup list=\"all\" > $archive_file");
    $n->add_action("modify off");

    $ret = $n->execute_pool($this->conf_error);

    $n->clean_pool();

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Get some part of configuration from the router
  */
  function get_info()
  {
    global $sms_sd_ctx;

    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'system property', 'SRPClient>');

    while (($line = get_one_line($buffer)) !== false)
    {
      if (preg_match('@^Version="(.*)"@', $line, $result) > 0)
      {
        $this->version = trim($result[1]);
      }
      else if (preg_match('@^Model="(.*)"@', $line, $result) > 0)
      {
        $this->model = trim($result[1]);
      }
      else if (preg_match('@^SerialNumber="(.*)"@', $line, $result) > 0)
      {
        $this->serial_number = trim($result[1]);
      }
    }

    return SMS_OK;
  }

  /**
   * Apply the configuration file passed in parameter into the router.
   * @param $configuration filepath of the .na configuration file.
   */
  function apply_conf($configuration, $prov)
  {

    echo "Entering apply_conf(" . $configuration . ")\n";

    $n = new nsrpc($this->sd);

    $n->add_action("modify on force");

    $n->add_action("config restore list=\"all\" refresh=1 < $configuration");

    $n->add_action("config status remove");
    $n->add_action("config status validate password=$this->validate_passwd");

    if ($prov)
    {
      // reboot if provisionning
      $n->add_action("system reboot");
    }
    else
    {
      $n->add_action("config network activate");
      $n->add_action("modify off");
    }

    $ret = $n->execute_pool($this->conf_error);

    $n->clean_pool();

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Recuperation d'informations generales sur l'equipement (model, version, numero de serie)
  * Récupération des fichiers issus du profil et du device (configuration et scripts)
  * Création d’une archive
  * Deploiement sur l’équipement (restore)
  * Sauvegarde sous SVN de la conf appliquee et des erreurs
  * Exécution des scripts
  */
  function update_conf($prov = false)
  {

    if ($this->sd->SD_HSRP_TYPE === 2)
    {
      // Nothing to do if it is a slave
      return SMS_OK;
    }

    $ret = $this->init_conf($prov);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    netasq_disconnect();

    $ret = $this->build_conf($prov);
    if ($ret !== SMS_OK)
    {
      if (!empty($this->log_msg))
      {
        set_log($this->log_level, $this->log_ref, $this->log_msg);
      }
      return $ret;
    }

    $na_archive = "{$this->spool_folder}/{$this->sdid}.na";
    $ret = $this->create_na_archive($this->spool_folder, $na_archive, "{$this->sdid}.na");
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    netasq_connect();

    // check HA state
    $isInactiveHA = $this->isInactiveHA();

    // pre apply script
    $ret = $this->apply_script('PRE_CONFIG');
    if ($ret !== SMS_OK)
    {
      exec_local(__FILE__ . ':' . __LINE__, "rm -f $na_archive 2>&1", $output);
      return $ret;
    }

    // right now, the connection is no longer usefull, disconnect to avoid problem if the device close the connection when applying the conf
    netasq_disconnect();

    $ret = $this->apply_conf($na_archive, $prov);
    if ($ret !== SMS_OK)
    {
      exec_local(__FILE__ . ':' . __LINE__, "rm -f $na_archive 2>&1", $output);
      return $ret;
    }

    $ret = exec_local(__FILE__ . ':' . __LINE__, "rm -f $na_archive 2>&1", $output);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $ret = $this->save_generated();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // wait the device become up after applying the conf
    $ret = $this->wait_until_device_is_up();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      echo "Update configuration for HA detected\n";

      // HA configuration, should sync the conf
      if ($isInactiveHA !== $this->isInactiveHA())
      {
        // HA firewalls have swapped
        echo "HA firewalls have swapped after conf update, swap back\n";
        $ret = $this->ha_wait_peer();
        if ($ret !== SMS_OK)
        {
          return $ret;
        }
        $ret = $this->ha_swap();
        if ($ret !== SMS_OK)
        {
          return $ret;
        }
        if ($isInactiveHA !== $this->isInactiveHA())
        {
          return ERR_SD_HA_SWAP;
        }
      }
    }

    // post apply script
    $ret = $this->apply_script('POST_CONFIG');
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      echo "Synchronize the configuration on the passive node\n";
      $this->ha_sync();
    }

    return $ret;
  }

  /**
   * Apply the license file passed in parameter into the router.
   * @param $license filepath of the license file.
   */
  function apply_license($license, $dummy)
  {
    echo "Entering apply_license($license)\n";

    $n = new nsrpc($this->sd);

    $n->add_action("modify on force");
    if ($this->sd->SD_HSRP_TYPE === 0)
    {
      $cmd = "system licence upload < $license";
    }
    else
    {
      $cmd = "system licence upload fwserial={$this->sd->SD_SERIAL_NUMBER} < $license";
    }
    $n->add_action($cmd);
    $n->add_action("modify off");
    $ret = $n->execute_pool($this->conf_error);
    $n->clean_pool();

    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $nsrpc_output = implode('', $this->conf_error);
    $reboot = is_reboot_needed($nsrpc_output, $cmd, 2);
    if ($reboot === true)
    {
      $n->add_action("modify on force");
      if ($this->sd->SD_HSRP_TYPE === 0)
      {
        $n->add_action("system reboot");
      }
      else
      {
        $n->add_action("ha reboot serial={$this->sd->SD_SERIAL_NUMBER}");
      }
      $n->add_action("modify off");
      $ret = $n->execute_pool($this->conf_error);
      $n->clean_pool();
    }
    return $ret;
  }

  /**
   * Apply the firmware file passed in parameter into the router.
   * @param $firmware filepath of the firmware file.
   */
  function apply_firmware($firmware, $do_backup)
  {
    echo "Entering apply_firmware($firmware)\n";

    $n = new nsrpc($this->sd);

    $n->add_action("modify on force");
    if ($do_backup)
    {
      if ($this->sd->SD_HSRP_TYPE === 0)
      {
        $n->add_action("system clone type=dump");
      }
      else
      {
        $n->add_action("system clone type=dump fwserial={$this->sd->SD_SERIAL_NUMBER}");
      }
    }
    if ($this->sd->SD_HSRP_TYPE === 0)
    {
      $n->add_action("system update upload < $firmware");
      $n->add_action("system update activate");
    }
    else
    {
      $n->add_action("system update upload fwserial={$this->sd->SD_SERIAL_NUMBER} < $firmware");
      $n->add_action("system update activate fwserial={$this->sd->SD_SERIAL_NUMBER}");
    }
    $n->add_action("modify off");

    $ret = $n->execute_pool($this->conf_error);

    $n->clean_pool();

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Mise a jour de l’équipement
  * Attente du reboot de l'equipement
  */
  function update_device(& $map, $map_name, $map_key, $func, $param)
  {
    $ret = get_map_from_xml("{$this->fmc_ent}/{$this->sdid}.xml", $map, $this->conf_error, $map_name);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // get file
    $file = $map[$map_key];
    if (empty ($file))
    {
      return SMS_OK;
    }
    $file_path = "{$this->fmc_repo}/{$file}";
    $ret = $this-> $func ($file_path, $param);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // wait the device become up after reboot
    return $this->wait_until_device_is_up(60, 30);
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Mise a jour de la licence de l’équipement
  * Attente du reboot de l'equipement
  */
  function update_license()
  {
    $this->get_info();

    // right now, the connection is no longer usefull
    netasq_disconnect();

    $ret = $this->update_device($this->license, 'License', 'License', 'apply_license', null);

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  * Mise a jour du firmware de l’équipement
  * Attente du reboot de l'equipement
  */
  function update_firmware($do_backup)
  {
    $this->get_info();

    if ($do_backup && (!$this->isBackupPartitionSupported()))
    {
      return ERR_SD_NO_BACKUP_PARTITION;
    }

    // right now, the connection is no longer usefull
    netasq_disconnect();

    $ret = $this->update_device($this->firmware, 'Firmware', 'Firmware', 'apply_firmware', $do_backup);

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function apply_script($key)
  {
    global $sms_sd_ctx;
    global $smarty_function;

    // get script
    if (empty ($this->scripts[$key]))
    {
      return SMS_OK;
    }
    $script = $this->scripts[$key];

    // Additional variable for data files:
    $add_vars["CLI_PREFIX"] = "{$this->cli_prefix}";
    $add_vars["ABONNE"] = "{$this->abonne}";

    // resolve script with configuration variables
    $script_path = "{$this->fmc_repo}/{$script}";
    echo "apply script $script_path\n";
    $resolved_template = resolve_template($this->sdid, $script_path, $add_vars, $smarty_function);
    if (empty ($resolved_template))
    {
      return ERR_LOCAL_PATTERN_NOT_FOUND;
    }

    // Save script to svn
    save_result_file($resolved_template, "$key.applied");

    // Go in write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

    // Apply script to the router
    $line = get_one_line($resolved_template);
    $ret_buffer = "";
    $ret = SMS_OK;
    while ($line !== false)
    {
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $line, 'SRPClient>');
      $ret_buffer .= "\n$buffer";
      if (is_error($buffer, $line) === true)
      {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": Command [$line] has failed in script $script_path:\n$buffer\n");
        $ret = ERR_SD_CMDFAILED;
        break;
      }
      $line = get_one_line($resolved_template);
    }
    save_result_file($ret_buffer, "$key.error");

    // Leave write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify off', 'SRPClient>');

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function exec_script($script, & $return_buf)
  {
    global $sms_sd_ctx;
    global $smarty_function;

    $this->get_info();

    $return_buf = '';

    // Additional variable for data files:
    $add_vars["CLI_PREFIX"] = "{$this->cli_prefix}";
    $add_vars["ABONNE"] = "{$this->abonne}";

    // resolve script with configuration variables
    $script_path = "{$this->fmc_repo}/{$script}";
    echo "execute script $script_path\n";
    $resolved_template = resolve_template($this->sdid, $script_path, $add_vars, $smarty_function);
    if (empty ($resolved_template))
    {
      return ERR_LOCAL_PATTERN_NOT_FOUND;
    }

    // Go in write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

    // Apply script to the router
    $line = get_one_line($resolved_template);
    $ret = SMS_OK;
    while ($line !== false)
    {
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $line, 'SRPClient>', 180000);
      $return_buf .= "\n$buffer";
      if (is_error($buffer, $line) === true)
      {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": Command [$line] has failed in script $script_path:\n$buffer\n");
        $ret = ERR_SD_CMDFAILED;
        break;
      }
      $line = get_one_line($resolved_template);
    }

    // Leave write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify off', 'SRPClient>');

    // TODO write execution result in database

    return $ret;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function provisioning()
  {
    return $this->update_conf(true);
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */
  function staging(& $staging_cli)
  {
    $staging_param['ncm_ip_addr'] = $this->ncm_ip_addr;
    $staging_param['validate_passwd'] = $this->validate_passwd;
    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      if ($this->sd->SD_HSRP_TYPE === 1)
      {
        $ha_password = "UBIqube-HA-$this->sdid-$this->partner_sdid";
      }
      else
      {
        $ha_password = "UBIqube-HA-$this->partner_sdid-$this->sdid";
      }
      $staging_param['ha_password'] = sha1($ha_password);
    }

    $staging_cli = PATTERNIZETEMPLATE('staging.tpl', $staging_param);
    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      if ($this->sd->SD_HSRP_TYPE === 1)
      {
        $staging_cli .= PATTERNIZETEMPLATE('staging_ha_master.tpl', $staging_param);
      }
      else if ($this->sd->SD_HSRP_TYPE === 2)
      {

        $staging_cli .= PATTERNIZETEMPLATE('staging_ha_slave.tpl', $staging_param);
      }
    }
    else
    {
      $staging_cli .= PATTERNIZETEMPLATE('staging_end.tpl', $staging_param);
    }

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *   Initialise some variables for generating the configuration
  */
  function init_conf($prov = false)
  {
    $this->get_info();

    if (!$prov)
    {
      $ret =  $this->check_serial_number();
      if ($ret !== SMS_OK)
      {
        if (!empty($this->log_msg))
        {
          set_log($this->log_level, $this->log_ref, $this->log_msg);
        }
        return $ret;
      }
    }

    $this->spool_folder = "/opt/sms/spool/fmc/{$this->sdid}"; // pas de '/' a la fin
    if (!is_dir($this->spool_folder))
    {
      $ret = exec_local(__FILE__ . ':' . __LINE__, "mkdir -p $this->spool_folder 2>&1", $output);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
    }
    else
    {
      // Clean up the directory
      $ret = exec_local(__FILE__ . ':' . __LINE__, "rm -rf $this->spool_folder/* 2>&1", $output);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
    }

    return SMS_OK;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *   Generate configuration files from database
  */
  function build_conf($prov)
  {
    // Get file based configuration
    $pflid = "{$this->cli_prefix}PR{$this->pflid}";
    $ret = $this->generate($pflid);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return $this->build_post_conf($prov);
  }

  // ------------------------------------------------------------------------------------------------
  /**
   *   Generate post configuration files
   */
   function build_post_conf($prov)
   {
     // For provisioning, add empty file Event/rules if not present
     if ($prov && !$this->event_rules_present)
     {
       $folder = $this->spool_folder;
       create_file("$folder/usr/Firewall/ConfigFiles/Event/rules", '');
     }

     return SMS_OK;
   }

  /**
   * Check if the SD is an inactive HA site
   */
  function isInactiveHA($mustConnect = false)
  {
    global $sms_sd_ctx;

    if ($this->sd->SD_HSRP_TYPE === 0)
    {
      echo "SD NOT HA\n";
      return false;
    }

    if ($mustConnect === true)
    {
      $ret = netasq_connect();
      if ($ret !== SMS_OK)
      {
        return false;
      }
    }

    // Compare serial number
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'system property', 'SRPClient>');
    $buffer = strstr($buffer, 'SerialNumber=');
    if ($buffer !== false)
    {
      if (preg_match('@^SerialNumber="(.*)"@', $buffer, $result) > 0)
      {
        $serial = trim($result[1]);
        echo "SD serial [{$this->sd->SD_SERIAL_NUMBER}]    Read serial [$serial]\n";
        if ($this->sd->SD_SERIAL_NUMBER !== $serial)
        {
          if ($mustConnect === true)
          {
            netasq_disconnect();
          }
          return true;
        }
      }
    }
    if ($mustConnect === true)
    {
      netasq_disconnect();
    }
    return false;
  }

  /**
   * Swap HA mode (active <-> passive)
   */
  function ha_swap()
  {
    global $sms_sd_ctx;

    $this->get_info();

    // Go in write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

    $cmd = 'ha setmode mode=passive';

    try
    {
      $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>', 5000);
    }
    catch (Exception | Error $e)
    {
      if ($e->getCode() === ERR_SD_CONN_CLOSED_BY_PEER || $e->getCode() === ERR_SD_CMDTMOUT)
      {
        // Normal case, HA SWAP OK, the device close the connection
        $ret = $this->wait_until_device_is_up();
        if ($ret !== SMS_OK)
        {
          return $ret;
        }

        // Go in write mode
        sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

        sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'ha setmode mode=normal serial=passive', 'SRPClient>');
        return SMS_OK;
      }
      else
      {
        throw new SmsException($e->getMessage(), $e->getCode());
      }
    }

    if (is_error($buffer, $cmd) === true)
    {
      // $cmd returns an error, HA SWAP failed
      sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$buffer\n");

      return ERR_SD_CMDFAILED;
    }

    // $cmd is OK, close the connection and reconnect
    $ret = $this->wait_until_device_is_up();
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    // Go in write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'ha setmode mode=normal serial=passive', 'SRPClient>');

    return SMS_OK;
  }

  function ha_wait_peer()
  {
    global $sms_sd_ctx;

    sleep(10);

    $cmd = 'ha info serial=passive';
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');
    $count = 30;
    while ($count > 0)
    {
      if ((strpos($buffer, '200 ') !== false) || ((strpos($buffer, 'State=Ready') === false) && (strpos($buffer, 'State=Running') === false ) && (strpos($buffer, 'Reply=1') === false)))
      {
        sleep(5);
        $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');
        $count--;
      }
      else
      {
        break;
      }
    }

    if ($count === 0)
    {
      return ERR_SD_CMDTMOUT;
    }

    return SMS_OK;
  }

  /**
   * Synchronize HA configurations
   */
  function ha_sync()
  {
    global $sms_sd_ctx;

    if ($this->sd->SD_HSRP_TYPE === 0)
    {
      return ERR_SD_CMDFAILED;
    }

    // Go in write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify on force', 'SRPClient>');

    $ret = sendexpectnobuffer(__FILE__.':'.__LINE__, $sms_sd_ctx, 'ha sync', 'SRPClient>');

    // Leave write mode
    sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, 'modify off', 'SRPClient>');

    return $ret;
  }


  // ------------------------------------------------------------------------------------------------
  /**
  * Check the serial number read from the router against the one in DB
  * Note : during the provisioning  $this->sd->SD_SERIAL_NUMBER can be empty but this is not an error
  */
  function check_serial_number()
  {
    if (empty($this->serial_number) ||
    (($this->sd->SD_HSRP_TYPE === 0) && (!empty($this->sd->SD_SERIAL_NUMBER)) && ($this->serial_number !== $this->sd->SD_SERIAL_NUMBER)) ||
    (($this->sd->SD_HSRP_TYPE !== 0) && (!empty($this->sd->SD_SERIAL_NUMBER)) && ($this->serial_number !== $this->sd->SD_SERIAL_NUMBER) && (!empty($this->partner_sd->SD_SERIAL_NUMBER)) && ($this->serial_number !== $this->partner_sd->SD_SERIAL_NUMBER)))
    {
      $this->log_level = '1'; // generate an alarm
      $this->log_ref = 'SERIALNUMBER';
      $this->log_msg = "Bad serial number, [{$this->serial_number}] instead of [{$this->sd->SD_SERIAL_NUMBER}]";
      return ERR_SD_BAD_SERIAL_NUMBER;
    }

    return SMS_OK;
  }

  /*
   * For do_get_config
  */
  function init_get_config($folder)
  {
    $this->spool_folder = $folder;
    if (!is_dir($this->spool_folder))
    {
      $ret = exec_local(__FILE__ . ':' . __LINE__, "mkdir -p $this->spool_folder 2>&1", $output);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
    }
    else
    {
      // Clean up the directory
      $ret = exec_local(__FILE__ . ':' . __LINE__, "rm -rf $this->spool_folder/* 2>&1", $output);
      if ($ret !== SMS_OK)
      {
        return $ret;
      }
    }

    return SMS_OK;
  }

  /*
   * Check on the device the backup partition
   * Must be connected
   */
  function isBackupPartitionSupported()
  {
    global $sms_sd_ctx;

    $cmd = 'system clone';
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd, 'SRPClient>');

    if (is_error($buffer, $cmd) === true)
    {
      sms_log_error(__FILE__.':'.__LINE__ . ": Command [$cmd] has failed:\n$buffer\n");
      return false;
    }

    return true;
  }

  function restore_from_old_revision($revision_id)
  {
    $ret = $this->init_conf();
    if ($ret !== SMS_OK)
    {
      netasq_disconnect();
      return $ret;
    }

    netasq_disconnect();

    echo("restore_from_old_revision revision_id: $revision_id\n");
    $restore_conf_file = "{$this->spool_folder}/{$this->sdid}_r{$revision_id}.na";

    $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --getfile {$this->sdid} na {$restore_conf_file} r{$revision_id}";

    $ret = exec_local(__FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK)
    {
      echo("no running conf found\n");
      unlink($restore_conf_file);
      return $ret;
    }

    if (!file_exists($restore_conf_file))
    {
      echo("no running conf found\n");
      return ERR_CONFIG_EMPTY;
    }

    $this->apply_conf($restore_conf_file, false);

    unlink($restore_conf_file);

    if ($this->sd->SD_HSRP_TYPE !== 0)
    {
      netasq_connect();
      echo "Synchronize the configuration on the passive node\n";
      $this->ha_sync();
      netasq_disconnect();
    }

    return SMS_OK;
  }

  function wait_until_device_is_up($nb_loop = 60, $initial_sec_to_wait = 30)
  {
    // wait the device become up after reboot
    $done = $nb_loop;
    sleep($initial_sec_to_wait); // Wait for the shutdown to be effective
    do
    {
      echo "waiting for the device, $done\n";
      sleep(5);
      try
      {
        netasq_connect($this->sd->SD_IP_CONFIG);
        break;
      }
      catch (Exception | Error $e)
      {
        $done--;
      }
    } while ($done > 0);

    if ($done === 0)
    {
      sms_log_error(__FILE__ . ':' . __LINE__ . ": The device stay DOWN\n");
      return ERR_SD_CMDTMOUT;
    }

    return SMS_OK;
  }

}

/**
 * @}
 */
?>