<?php 
/*
 * Date : Jun 26, 2012
*/

function get_wsa_error($err_string)
{
  $error_wsa_string = htmlentities(substr($err_string, strpos($err_string, 'ERROR:'), (strlen($err_string) - strpos($err_string, '*****'))*(-1)));
  return $error_wsa_string;
}
?>