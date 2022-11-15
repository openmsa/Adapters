<?php

/*
 * Version: 1.0
 * Created: Oct 6, 2022
 * Author abr@ubiqube.com
 */

require_once load_once("parserd/filter/$model", 'dictionary.php');


function set_fields(&$fields, &$records) {
	global $timezone;

	$fields ['rawlog'] = $records ['rawlog'];
	$fields ['Type'] = 'generic';
	$fields ['subtype'] = 'RFC3164';

  //-------------------------
  //- HOSTNAME
  //-------------------------
  if (!empty ($records['orig'])) {
    $fields['hostname'] = str_replace(':','',$records['orig']);
  }

	//-------------------------------
	//----     Date
	//-------------------------------
	if (empty($records['timestamp'])) {
	  $fields['Date'] = date('Y-m-d\TH:i:s');
	} else if (!empty($records['date']) && !empty($records['time'])){
	  $fields['Date'] = $records['date'] . 'T' . $records['time'];
	}
	$fields['Date'] .= $timezone;

}