<?php
/*
 * Version: $Id: do_checkprovisioning.php 23483 2009-11-03 09:11:46Z tmt $
 * Created: Jun 27, 2008
 * Available global variables
 *  $sms_sd_info   sd_info structure
 *  $sms_csp       pointer to csp context to send response to user
 *  $sms_module    module name (for patterns)
 */

/**
 * decompress .na archive to a destination folder
 * @param $puid			A unique identifier (used to generate temporary file.  The thread ID is a good idea.
 * @param $parchive		The archive (.na) to decompress
 * @param $pfolder		The folder where to decompress files
 * Note : use star instead of tar to be compatible with freebsd, sometimes tar doesn't work
 */
function netasq_unarchive_conf($puid, $parchive, $pfolder)
{
  echo "decompressing $parchive into $pfolder\n";
  $tgz = "/opt/sms/spool/tmp/$puid.netasq_archive.tgz";

  $ret = exec_local(__FILE__.":".__LINE__, "cd /opt/sms/bin/netasq && ./decbackup -i $parchive -o $tgz", $output);
  if ($ret !== SMS_OK)
  {
    return $ret;
  }

  echo "create destination folder ($pfolder) if not existant\n";
  $ret = exec_local(__FILE__.":".__LINE__, "mkdir -p $pfolder", $output);
  if ($ret !== SMS_OK)
  {
    exec_local(__FILE__.":".__LINE__, "rm -f $tgz", $output);
    return $ret;
  }

  echo "untar temporary tgz ($tgz)\n";
  $ret = exec_local(__FILE__.":".__LINE__, "cd $pfolder && star zoxf $tgz", $output);
  if ($ret !== SMS_OK)
  {
    exec_local(__FILE__.":".__LINE__, "rm -rf $tgz $pfolder", $output);
    return $ret;
  }

  echo "changing access to the files created\n";
  $ret = exec_local(__FILE__.":".__LINE__, "chmod -R 775 $pfolder", $output);
  if ($ret !== SMS_OK)
  {
    exec_local(__FILE__.":".__LINE__, "rm -rf $tgz $pfolder", $output);
    return $ret;
  }

  echo "remove temporary tgz ($tgz)\n";
  $ret = exec_local(__FILE__.":".__LINE__, "rm -f $tgz", $output);
  if ($ret !== SMS_OK)
  {
    exec_local(__FILE__.":".__LINE__, "rm -rf $pfolder", $output);
    return $ret;
  }

  return SMS_OK;
}

?>
