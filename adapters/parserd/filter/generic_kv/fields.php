<?php


/**
 * Generic key value parser
 */


/*
 * translate the timezone from the rawlog to a standard format
 * (GMT<sign><shift-hour>:<shift-minute>)<string>
 * (GMT)<string>
 *
 * sign is one char - or +
 * <shitf-hour> is 1 or 2 ciphers
 * <shift-minute> is is 2 ciphers
 * <string> rest of the timezone value, mostly state, region or town
 *
 * Only things inside parenthesis will be analyzed and transformed in the expected format :
 * <sign><shift-hour><shift-minute>
 *
 * examples
 * (GMT-4:00)Atlantic Time(Canada)
 * (GMT)Greenwich Mean Time: Dublin,Edinburgh,Lisbon,London
 * (GMT+1:00)Brussels,Copenhagen,Madrid,Paris
 *
 * In a rawlog
 * v009xxxxdate=2020-03-10 time=17:19:49 log_id=10000018 msg_id=000000601741 device_id=FVBAWS0002d47ff9 vd="root" timezone="(GMT+1:00)Brussels,Copenhagen,Madrid,Paris" timezone_dayst="GMTc-1" type=event subtype="system" pri=notice trigger_policy="" user=admin ui=sshd action=logout status=success msg="User admin logged out from ssh(92.103.182.20)"
 *
 * In this case GMT+1:00 will be translated to +0100
 *
 */
function format_timezone($raw_timezone, $timezone)
{
  $matches = array();

  $ret = preg_match('/(\(GMT([-+]{1})([0-9]{1,2}):([0-9]{1,2})\))|(\(GMT\)).*/', $raw_timezone, $matches);
  if ($ret)
  {
    if (!empty($matches[5]))
    {
      // GMT
      return '+0000';
    }
    else
    {
      $sign = $matches[2];
      $shift_hours = $matches[3];
      $shift_minutes = $matches[4];
      if (strlen($shift_hours) == 1)
      {
        // add a 0 header
        $shift_hours = "0{$shift_hours}";
      }
      if (strlen($shift_minutes) == 1)
      {
        // add a 0 header
        $shift_minutes = "0{$shift_minutes}";
      }
      return "{$sign}{$shift_hours}{$shift_minutes}";
    }
  }

  return $timezone;
}

//
function set_fields(&$fields, &$records)
{
  global $timezone;
  
//	debug_dump($records, "\set_fields RECORDS:\n");

  $fields['rawlog'] = $records['rawlog'];

  //-------------------------------
	//----     Date
	//-------------------------------
	if (empty($records['timestamp'])) {
		$fields['Date'] = date('Y-m-d\TH:i:s');
	} else if (!empty($records['date']) && !empty($records['time'])) {
		$fields['Date'] = $records['date'] . 'T' . $records['time'];
	}

  // - HOSTNAME
	if (!empty($records['orig'])) {
		$fields['hostname'] = str_replace(':', '', $records['orig']);
	}
  // - TYPE
	$fields['Type'] = 'VNOC';
	$fields['subtype'] = 'DOCKER';

  if (!empty($records['type'])) {
    $fields['docker_event/type'] = $records['type'];
  }
  if (!empty($records['status'])) {
    $fields['docker_event/status'] = $records['status'];
  }
  if (!empty($records['from'])) {
    $from = $records['from'];
    $pieces = explode(":", $from);
    $fields['docker_event/service'] = $pieces[0];
    $fields['docker_event/image'] =  $from;
  }
  if (!empty($records['ID'])) {
    $fields['docker_event/container_id'] = $records['ID'];
  }
  if (!empty($records['action'])) {
    $fields['docker_event/action'] = $records['action'];
  }

}
?>
