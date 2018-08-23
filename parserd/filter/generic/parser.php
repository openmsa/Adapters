<?php


require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

function parse_line(& $line) {
  return parse_txt_line_no_analyze($line);
}

?>