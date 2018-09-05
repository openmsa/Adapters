<?php

function my_getcsv($string, $delimiter = ",", $enclosure = '"', $escape = "\\", $remove_enclosure = TRUE)
// slow
{
  $array      = array();
  $count      = 0;

  $characters = preg_split('//', $string);
  $flag_encl  = FALSE;
  $flag_esc   = FALSE;
  $str        = '';
  foreach($characters as $char){
    if($flag_esc === TRUE){
      $str .= $char;
      $flag_esc = FALSE;
    }
    else if($char === $escape){
      // not include escape character
      $flag_esc = TRUE;
    }
    else if($flag_encl === FALSE && $char === $enclosure){ // in enclosed
      if(!$remove_enclosure){
        $str .= $char;   // include enclosure
      }
      $flag_encl = TRUE;
      // TENUKI: "aa"a" " <= OK
    }
    else if($flag_encl === FALSE && $char === $delimiter){  // split
      $array[$count] = $str;   // push
      $count++;
      $str = '';
    }
    else{  //flag_encl === TRUE or not delimiter, escape
      if($char === $enclosure && $remove_enclosure){
        // do nothing
      }
      else{
        $str .= $char;
      }
      if($char === $enclosure){
        $flag_encl = FALSE;
      }
    }
  }
  $array[$count] = $str; // finally

  return $array;
}

function convert_dateandtime_hex_to_string($date_candidate)
{
  // this code assumes that is 16 or 22 of hexadeciaml digit without white spaces
  $dateandtime['year']   = substr($date_candidate, 0, 4);  // value 0-65535
  $dateandtime['month']  = substr($date_candidate, 4, 2);  // value 1-12
  $dateandtime['day']    = substr($date_candidate, 6, 2);  // value 1-31
  $dateandtime['hour']   = substr($date_candidate, 8, 2);  // value 0-23
  $dateandtime['minute'] = substr($date_candidate, 10, 2); // value 0-59
  $dateandtime['second'] = substr($date_candidate, 12, 2); // value 0-60 (60 - leap second)
  $dateandtime['dsec']   = substr($date_candidate, 14, 2); // value 0-9 (16th hexadecimal digit)
  $dateandtime['tdsign'] = substr($date_candidate, 16, 2); // '+' or '-' (oops! its an ascii character: 2b or 2d)
  $dateandtime['tdhour'] = substr($date_candidate, 18, 2); // value 0-11
  $dateandtime['tdmin']  = substr($date_candidate, 20, 2); // value 0-59 (22th hexadecimal digit)

  $date_string = sprintf("%02d-%02d-%02dT%02d:%02d:%02d", 
                             hexdec($dateandtime['year']),
                             hexdec($dateandtime['month']),
                             hexdec($dateandtime['day']),
                             hexdec($dateandtime['hour']),
                             hexdec($dateandtime['minute']),
                             hexdec($dateandtime['second']));

  if(hexdec($dateandtime['dsec']) != 0){
    $date_string .= sprintf(".%d", hexdec($dateandtime['dsec'])) . "00"; // FORCE PRECISION - non-standard
  }

 // 11 octet style
 if(!empty($dateandtime['tdsign']) && !empty($dateandtime['tdhour']) && !empty($dateandtime['tdmin'])){
    $date_string .= sprintf("%c%02d:%02d", 
                             hexdec($dateandtime['tdsign']),
                             hexdec($dateandtime['tdhour']),
                             hexdec($dateandtime['tdmin']));
  }
  else{
    $date_string .= 'Z';    // !!!! TENUKI -  FORCE UTC - non-standard
    // must to be changed to specify system's Timezone
  }

  return $date_string;
}

// test code
// echo convert_dateandtime_hex_to_string('07e40b0a0c282801') . "\n";
// echo convert_dateandtime_hex_to_string('07e40b0a0c2828092b0900') . "\n";


?>
