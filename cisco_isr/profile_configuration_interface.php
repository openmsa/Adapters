<?php
/*
 * Version: $Id: profile_configuration_interface.php 39436 2011-02-10 10:57:34Z oda $
 * Created: Feb 12, 2009
 */

// Profile management interface
require_once 'smserror/sms_error.php';

/**
 * @addtogroup ciscoupdate
 * @{
 */

/**
 * Profile management interface.
 * The profiles are responsible for the configuration generation.
 */
class profile_configuration_interface
{
  var $name;          // Name of the profile

  /**
   * Get the profile name
   */
  function get_name()
  {
    return $this->name;
  }

  /**
   * Get the configuration for the profile.
   * This configuration is parsed later to generate delta with
   * running configuration or previously generated configuration.
   * @param $configuration  configuration buffer to fill
   */
  function get_conf(&$configuration)
  {
    return SMS_OK;
  }

  /**
   * Get the configuration to force before the configuration.
   * This configuration is applied without modification (no deltas is performed)
   * @param $configuration  configuration buffer to fill
   */
  function get_pre_conf(&$configuration)
  {
    return SMS_OK;
  }

  /**
   * Get the configuration to force after the configuration
   * This configuration is applied without modification (no deltas is performed)
   * @param $configuration  configuration buffer to fill
   * @param $delta_conf     previously generated delta configuration
   */
  function get_post_conf(&$configuration, $delta_conf = '')
  {
    return SMS_OK;
  }

  /**
   * Get the configuration parser used to compare configurations
   * (the running and the generated ones)
   */
  function get_parser()
  {
    return null;
  }

  /**
   * Get the configuration parser used to fullfill variables
   * to generate the configuration
   * Because not all the decisions can be made on configuration compare
   * in the pre and post config some info from the running conf are needed
   */
  function parse_running_conf($running_conf)
  {
    return SMS_OK;
  }
  
  /**
   * Get the configuration parser
   */
  function get_parser_clean()
  {
    return $this->get_parser();
  }

  /**
   * true if the profile is active.
   * When the profile is active, the configuration has to be managed, else the configuration is left unchanged.
   */
  function is_active()
  {
    return false;
  }

}

/**
 * @}
 */

?>
