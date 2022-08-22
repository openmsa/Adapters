<?php
require_once 'smsd/sms_common.php';

require_once load_once('cisco_fmc_rest', 'adaptor.php');
require_once load_once('cisco_fmc_rest', 'me_apply_conf.php');

require_once "$db_objects";

class MeConfiguration
{
  var $sdid; // ID of the SD to update

  /**
	* Constructor
	*/
  function __construct($sdid)
  {
    $this->sdid = $sdid;
  }

  /**
	* Get running configuration from the router
	* Actually an export of the configuration
	*/
  function get_running_conf()
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
	 * Mise a jour de la licence
	 * Attente du reboot de l'equipement
	 */
  function update_license()
  {
  	return ERR_SD_NOT_SUPPORTED;
  }
}

?>