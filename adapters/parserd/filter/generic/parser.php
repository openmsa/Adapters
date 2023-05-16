<?php


require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('parserd', 'common.php');
require_once load_once('parserd', 'actions.php');

<<<<<<< Updated upstream

=======
>>>>>>> Stashed changes
function parse_line(& $line) {
  echo("generic parser \n LINE: \n $line");
  return parse_txt_line_no_analyze($line);
}

?>
