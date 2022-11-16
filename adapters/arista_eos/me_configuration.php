<?php
require_once 'smsd/sms_common.php';
require_once 'smsd/pattern.php';

require_once load_once('arista_eos', 'adaptor.php');
require_once load_once('arista_eos', 'me_apply_conf.php');


require_once "$db_objects";
class me_configuration
{
  var $conf_path; // Path for previous stored configuration files
  var $sdid; // ID of the SD to update
  var $running_conf; // Current configuration of the router
  var $profile_list; // List of managed profiles
  var $previous_conf_list; // Previous generated configuration loaded from files
  var $conf_list; // Current generated configuration waiting to be saved
  var $addon_list; // List of managed addon cards
  var $fmc_repo; // repository path without trailing /
  var $sd;
  var $is_ztd;

  /**
	* Constructor
	*/
  function __construct($sdid, $is_provisionning = false)
  {
    $this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    $this->conf_pflid = 0;
    $this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    $net = get_network_profile();
    $this->sd = &$net->SD;
  }

  /**
	* Get running configuration from the router
	*/
  function get_running_conf()
  {
        global $sms_sd_ctx;
        // Download the configuration file
        $http_header_str = "Content-Type: application/json|Accept: application/json";

        $sms_sd_ctx->http_header_list = explode("|", $http_header_str);
        $req="{\"ins_api\": {\"version\": \"1.0\",\"type\": \"cli_conf\",\"chunk\": \"0\",\"sid\": \"sid\",\"input\": \"checkpoint file msa_backup\",\"output_format\": \"json\"}}";
        $cmd = "POST#/ins#".$req;
        $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);

        $sms_sd_ctx->http_header_list = explode("|", $http_header_str);
        $req="{\"ins_api\": {\"version\": \"1.0\",\"type\": \"cli_conf\",\"chunk\": \"0\",\"sid\": \"sid\",\"input\": \"show file msa_backup\",\"output_format\": \"json\"}}";
        $cmd = "POST#/ins#".$req;
        $sms_sd_ctx->send(__FILE__ . ':' . __LINE__, $cmd);
        $running_conf_array = $sms_sd_ctx->get_raw_json();
        $running_conf_array = json_decode($running_conf_array,true);
        if (isset($running_conf_array['ins_api']['outputs']['output']['body'])){
            $body = $running_conf_array['ins_api']['outputs']['output']['body'];
            $body = preg_replace('/!Time:(.*)/m', '', $body);
        }else{
            $body=$running_conf_array;
        }
        return $body;
   }

  /**
   *
   * @return string
   */
  function get_staging_conf()
  {
  	return '';
  }

  /**
   *
   * @param string $param
   * @return string
   */
  function update_firmware($param = '')
  {
  	return ERR_SD_NOT_SUPPORTED;
  }

  /**
   *
   * @param string $revision_id
   * @return string|Ambigous <unknown, string>
   */
  function get_generated_conf($revision_id = NULL)
  {
    if (!isset($revision_id))
    {
      return "";
    }
    echo ("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd = "/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
    echo ($get_saved_conf_cmd . "\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__, $get_saved_conf_cmd, $output);
    if ($ret !== SMS_OK)
    {
      echo ("no running conf found\n");
      return $ret;
    }

    $res = array_to_string($output);
    return $res;
  }

  /**
   *
   * @param unknown $configuration
   * @return unknown
   */
  function restore_conf($configuration)
  {
    $ret = me_apply_restore_conf($configuration);
    return $ret;
  }

  /**
	* Generate the general pre-configuration
	* @param $configuration   configuration buffer to fill
	*/
  function generate_pre_conf(&$configuration)
  {
  	return SMS_OK;
  }

  /**
	* Generate a full configuration
	* Uses the previous conf if present to perform deltas
	*/
  function generate(&$configuration, $use_running = false)
  {
    //$configuration .= "! CONFIGURATION GOES HERE\n";
    get_conf_from_config_file($this->sdid, $this->conf_pflid, $configuration, 'INIT_CONFIG', 'Configuration');
    return SMS_OK;
  }

  /**
	* Generate the general post-configuration
	* @param $configuration   configuration buffer to fill
	*/
  function generate_post_conf(&$configuration)
  {
   return SMS_OK;
  }

  /**
	*
	*/
  function build_conf(&$generated_configuration)
  {
    $ret = $this->generate_pre_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }
    $ret = $this->generate($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    $ret = $this->generate_post_conf($generated_configuration);
    if ($ret !== SMS_OK)
    {
      return $ret;
    }

    return SMS_OK;
  }

  
  function update_conf()
  {
    $ret = $this->build_conf($generated_configuration);

    if (!empty($generated_configuration))
    {
    	$ret = send_configuration_file($generated_configuration);
    }

    return $ret;
  }

  function provisioning()
  {
    return $this->update_conf();
  }

  function reboot($event, $params = '')
  {
  	return ERR_SD_NOT_SUPPORTED;
  }

  /**
	 * Mise a jour de la licence
	 * Attente du reboot de l'equipement
	 */
  function update_license()
  {
  	return ERR_SD_NOT_SUPPORTED;
  }
}

?>
