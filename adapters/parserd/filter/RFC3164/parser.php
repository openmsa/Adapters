<?php
/*
 * Version: 1.0
 * Created: Oct 6, 2022
 * Author abr@ubiqube.com
 *
 *  $filetoparse
 *  $sms_module         module name (for patterns)
 *  $SMS_RETURN_BUF    string buffer containing the result
 */

 /* The BSD syslog Protocol : https://datatracker.ietf.org/doc/html/rfc3164  */

/*
Logs look like that:

Oct  6 09:02:47 ip-172-31-0-52 sshd[4879]: Accepted publickey for centos from 81.1.3.125 port 53205 ssh2: ED25519 SHA256:P7aD6qXfDMzqVc3jmJdY9HG/OV+GoO9RGChCuLfN83o
Oct  6 09:02:47 ip-172-31-0-52 sshd[4879]: pam_unix(sshd:session): session opened for user centos by (uid=0)
Oct  6 09:03:12 ip-172-31-0-52 sshd[4881]: Received disconnect from 81.1.3.125 port 53205:11: disconnected by user
Oct  6 09:03:12 ip-172-31-0-52 sshd[4881]: Disconnected from 81.1.3.125 port 53205
 */

//New Parser
/*
#Fields: id Month Day Hour hostname Info
<134>Oct 6 04:27:10 P05HSVRHE05-AI1801KVDC iLO5 Host REST logout: System Administrator
*/

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('parserd', 'common.php');
require_once load_once('parserd', 'actions.php');
require_once load_once("parserd/filter/$model", 'fields.php');


$count['bad_record'] = 0;
$count['no_action'] = 0;

function parse_line(&$line)
{
  global $record_names;
  global $actions;
  global $count;
  global $fields;
  $result = new PARSER_RESULT();

  $fields_line = "id Month Day Hour orig Info";

  $line = trim($line);

  //FOR ES
  if ($line[0] != '#')
  {
  	$records['rawlog'] = $line;
  }
  $parse_failed = false;
  //--------------------------------------------------------------------------------
  /* 1 - parse the line */
  // search for fields definition

  $record_names = preg_split("@\s+@", $fields_line);

  echo "$line\n";
  var_dump($record_names);
  //echo "$regexp\n";

  // will contain the pos of the last field
  $end_matches = 0;

  if (preg_match_all('/\s*("[^"]*"|<[^>]*>|\S+)/', $line, $records_tmp, PREG_OFFSET_CAPTURE) > 0)
  {
    $index = 0;
    foreach ($record_names as $record_name)
    {
    	if(empty($records_tmp[1][$index])){
    		$val = null;
    	}else{
    		$val = $records_tmp[1][$index][0];
    		$end_matches = max($end_matches, $records_tmp[1][$index][1]);
    	}

      $index += 1;
      if (strpos($val, '"') === 0)
      {
        // quoted value
        $val = substr($val, 1, strlen($val) - 2);
      }
      if($val == '-')
      {
      	$val = '';
      }
      $records[$record_name] = $val;
    }

    // Info is last field and contains all the rest
    if (!empty($records['Info']))
    {
      if ($records['Info'] == 'Info:')
      {
        $records['Info'] = substr($line, $end_matches + 5);
      }
      else
      {
        $records['Info'] = substr($line, $end_matches);
      }
    }

    //$records['rawlog'] = $line;
  }
  else
  {
    // bad record
    $count['bad_record']++;
    $parse_failed = true;
  }

  //var_dump($records);

  //--------------------------------------------------------------------------------
  /* rules application */
  set_fields($fields, $records);

  echo "$line\n";
  debug_dump($records, '$records\n');


	$action_name = "Insert_into_logs";
  $action = $actions[$action_name];
  $result->ACTION = $action_name;
  $result->TYPE = $action['type'];
  $result->DB_TABLE = $action['table'];
  $result->DB_FIELDS = convert_to_database_fields($action_name, $fields);

  debug_dump($result, '$result\n');

  return $result;
}

?>
