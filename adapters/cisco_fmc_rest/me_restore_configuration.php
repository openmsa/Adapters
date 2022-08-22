<?php

require_once 'smsd/sms_common.php';
require_once load_once('cisco_fmc_rest', 'me_connect.php');

require_once "$db_objects";

class MeRestoreConfiguration
{
  var $sdid;                // ID of the SD to update
  var $runningconf_to_restore;             //running conf retrieved from SVN /
  var $revision_id;         // revision id to restore
  var $job_id;

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  */
  function __construct($sdid)
  {
    $this->sdid = $sdid;
  }

?>