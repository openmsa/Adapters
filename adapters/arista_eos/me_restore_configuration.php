<?php


require_once load_once ( 'arista_eos', 'common.php' );
require_once load_once ( 'arista_eos', 'me_connect.php' );
require_once load_once ( 'arista_eos', 'apply_errors.php' );

require_once "$db_objects";

class device_restore_configuration
{
  var $sdid;                // ID of the SD to update
  var $sd;                  // Current SD
  var $running_conf;        // Current configuration of the router
  var $runningconf_to_restore;             //running conf retrieved from SVN /

  // ------------------------------------------------------------------------------------------------
  /**
  * Constructor
  */
  function __construct($sdid)
  {
    //$this->conf_path = $_SERVER['GENERATED_CONF_BASE'];
    $this->sdid = $sdid;
    //$this->fmc_repo = $_SERVER['FMC_REPOSITORY'];
    //$this->fmc_ent = $_SERVER['FMC_ENTITIES2FILES'];

    $net = get_network_profile();
    $this->sd=&$net->SD;
  }

  // ------------------------------------------------------------------------------------------------
  /**
  *
  */

  function generate_from_old_revision($revision_id)
  {
    echo("generate_from_old_revision revision_id: $revision_id\n");
    $this->revision_id = $revision_id;

    $get_saved_conf_cmd="/opt/sms/script/get_saved_conf --get $this->sdid r$this->revision_id";
    echo($get_saved_conf_cmd."\n");

    $ret = exec_local(__FILE__ . ':' . __LINE__,  $get_saved_conf_cmd, $output);
  if ($ret !== SMS_OK) {
    echo("no running conf found\n");
      return $ret;
  }

  $res=array_to_string($output);

  $this->runningconf_to_restore = $res;

  $this->runningconf_to_restore = str_replace("SMS_OK", "", $this->runningconf_to_restore);
    return SMS_OK;
  }


  function restore_conf() {
    global $apply_errors;

    global $sms_sd_ctx;
    $ret = SMS_OK;

    echo "SCP mode configuration\n";

    // Request flash space on router
    $file_name = "{$this->sdid}.cfg";
    $full_name = $_SERVER ['TFTP_BASE'] . "/" . $file_name;

    $ret = save_file ( $this->runningconf_to_restore, $full_name );
    if ($ret !== SMS_OK) {
      return $ret;
    }
    $ret = save_result_file ( $this->runningconf_to_restore, 'conf.applied' );
    if ($ret !== SMS_OK) {
      return $ret;
    }
    try {
      $ret = scp_to_router ( $full_name, $file_name );
      if ($ret === SMS_OK) {
        // SCP OK
        $SMS_OUTPUT_BUF = copy_to_running ( "configure replace $file_name" );
        save_result_file ( $SMS_OUTPUT_BUF, "conf.error" );

        foreach ( $apply_errors as $apply_error ) {
          if (preg_match ( $apply_error, $SMS_OUTPUT_BUF  ) > 0) {
            $apply_error = preg_replace ('/@/','', $apply_error);
            sms_log_error ( __FILE__ . ':' . __LINE__ . ": [[!!!Error found for $apply_error:  $SMS_OUTPUT_BUF !!!]]\n" );
            preg_match ( "(".$apply_error.".*)", $SMS_OUTPUT_BUF, $matches );
            return  $matches[0] ;
            #or to get full error message 
            #list($dummy, $erreur) = preg_split($SMS_OUTPUT_BUF, $apply_error, 2);
            #if ($erreur){
            #  return  $erreur ;
            #}else{
            #  return  $SMS_OUTPUT_BUF ;
            #}
          }
        }

        unset ( $tab );
        $tab [0] = $sms_sd_ctx->getPrompt ();
        $tab [1] = "]?";
        $tab [2] = "[confirm]";
        $index = sendexpect ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "delete $file_name no-prompt", $tab );
        while ( $index !== 0 ) {
          $index = sendexpect ( __FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab );
        }
        echo "restore_conf method is finished";
        return $ret;
      } else {
        // SCP ERROR
        sms_log_error ( __FILE__ . ':' . __LINE__ . ":SCP Error $ret\n" );
        return $ret;
      }
    } catch ( Exception | Error $e ) {
      if (strpos ( $e->getMessage (), 'connection failed' ) !== false) {
        return ERR_SD_CONNREFUSED;
      }
      sms_log_error ( __FILE__ . ':' . __LINE__ . ":SCP Error $ret\n" );
    }
    return SMS_OK;
  }
}

?>
