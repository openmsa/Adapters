<?php

/**
 * Generic key value parser
 */


/*
 * Log looks like that:
<13>Mar 22 16:41:10 10.242.225.12 Type=container Status=start From=telegraf:latest@sha256:9f2974a238166819844013871b97ee75f7faabc802fef526fb2eec10916a7d76 ID=fcb995faf1d34fac347f5f5f2b7838077d82c48f533982cb60e21c893ba18c80 Action=start
 *
 */


require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('parserd', 'common.php');
require_once load_once('parserd', 'actions.php');
require_once load_once("parserd/filter/$model", 'fields.php');

$count['full_ok'] = 0;
$count['bad_record'] = 0;
$count['no_action'] = 0;

function parse_line(&$fields, &$line) {
  
  $result = new PARSER_RESULT();
  
	$line = trim($line);
  $line = utf8_encode($line); // useful when Japanese characters are present in the logs
	debug_dump($line, "\n$line:\n");
	//--------------------------------------------------------------------------------
	/* 1 - parse the line */
  $records = get_records($line);
  if ($records === false)
  {
    return false;
  }

	//--------------------------------------------------------------------------------
	/* 2 - fields tranformation */
	set_fields($fields, $records);

	//--------------------------------------------------------------------------------
	/* 3 - database */
  $action_name = 'Insert_into_logs';
	$result->ACTION = $action_name;
	if (isset($fields['type'])) {
	  $type = $fields['type'];
	  if (isset($fields['subtype'])) {
	    $type .= "-{$fields['subtype']}";
	  }
	} else {
	  $type = 'EVENT';
	}
  $result->TYPE = $type;
  $result->DB_TABLE = 'logs';
  $result->DB_FIELDS = $fields;

	//--------------------------------------------------------------------------------
	/* 4 - debug */
	debug_dump($records, '$records');
	debug_dump($fields, '$fields');
	debug_dump($result, '$result');

	//--------------------------------------------------------------------------------
	/* 5 - return */
	return $result;

}

/**
 * Current version of the parser with complex regex
 * @param unknown $line
 * @return boolean|unknown
 */
function get_records(&$line)
{
  global $count;

  $pattern = "@(?<name>[\w]+)=(?<value>[A-Za-z0-9_()./:-]+)|(?<namestr>[\w]+)=\"(?<valuestr>[^\"]*)\"@";
  if (preg_match_all($pattern, $line, $records_tmp) > 0)
  {
    //it's mean that $line is a type of log
    //the next step is to write value in an array
    //in case ?<name>[\w]+)=(?<value>[A-Za-z0-9_:-]+) works, name is on $records_tmp[1][$i] and value $records_tmp[2][$i]
    //whereas when (?<namestr>[\w]+)=\"(?<valuestr>[^\"]+) works, name is on $records_tmp[3][$i] and value $records_tmp[4][$i]
    for ($i = 0; $i < sizeof($records_tmp[0]); $i++)
    {
      if (!empty($records_tmp[1][$i]))
      {
        if (strpos($records_tmp[1][$i], 'date') !== false)
        {
          // Special case for logs fetched with REST API
          $records['date'] = $records_tmp[2][$i];
        }
        else
        {
          // priority to first value
          if (empty($records[$records_tmp[1][$i]]))
          {
            $records[$records_tmp[1][$i]] = $records_tmp[2][$i];
          }
        }
      }
      else
      {
        // priority to first value
        if (empty($records[$records_tmp[3][$i]]))
        {
          $records[$records_tmp[3][$i]] = $records_tmp[4][$i];
        }
      }
    }
    $count['full_ok']++;
    $records['rawlog'] = $line;
  }
  else
  {
    // bad record
    $count['bad_record']++;
    return false;
  }

  return $records;
}

/**
 * Call binary version of the parser
 */
function get_records3(&$line)
{
  global $count;

  $records_line = sms_parse_name_value_log($line);
  $records_tmp = explode('|', $records_line);
  for ($i = 0; $i < sizeof($records_tmp); $i+=2) {
    if (!empty($records_tmp[$i])) {
      $records[$records_tmp[$i]] = $records_tmp[$i+1];
    }
  }
  $count['full_ok']++;
  $records['rawlog'] = $line;

  return $records;
}

/**
 * Call simplified regex
 * @param unknown $line
 * @return unknown
 */
function get_records2(&$line)
{
  global $count;

  $pattern = "@(?<name>[a-z]+)=(?<value>[^ ]+)|(?<namestr>[a-z]+)=\"(?<valuestr>[^\"]*)\"@";
  if (preg_match_all($pattern, $line, $records_tmp) > 0)
  {
    //it's mean that $line is a type of log
    //the next step is to write value in an array
    //in case ?<name>[\w]+)=(?<value>[A-Za-z0-9_:-]+) works, name is on $records_tmp[1][$i] and value $records_tmp[2][$i]
    //whereas when (?<namestr>[\w]+)=\"(?<valuestr>[^\"]+) works, name is on $records_tmp[3][$i] and value $records_tmp[4][$i]
    for ($i = 0; $i < sizeof($records_tmp[0]); $i++)
    {
      if (!empty($records_tmp[1][$i]))
      {
        // priority to first value
        if (empty($records[$records_tmp[1][$i]]))
        {
            $records[$records_tmp[1][$i]] = $records_tmp[2][$i];
        }
      }
      else
      {
        // priority to first value
        if (empty($records[$records_tmp[3][$i]]))
        {
            $records[$records_tmp[3][$i]] = $records_tmp[4][$i];
        }
      }
    }
  }
  
  $count['full_ok']++;
  $records['rawlog'] = $line;
  return $records;
}

?>
