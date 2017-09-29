<?php
/*
 * Date : Jun 26, 2012
 */
function get_esa_error($err_string)
{
  $error_esa_string = htmlentities(substr($err_string, strpos($err_string, 'ERROR:'), (strlen($err_string) - strpos($err_string, '*****')) * (-1)));
  return $error_esa_string;
}
function format_esa_error($err_string)
{
  $buff1 = str_replace(array(
      "[",
      "loadconfig config.xml",
      "]"
  ), "", $err_string);
  return $buff1;
}

?>
