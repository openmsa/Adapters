<?php
/*
 * Version: $Id$
* Created: April 27, 2011
*/
require_once 'smsd/sms_common.php';

// smarty functions
$smarty_function = array(
      'UP' => 'do_sendfiletorouter',
      'DOWN' => 'do_sendfiletosoc'
);

function do_sendfiletosoc($params, &$smarty)
{
  if (!empty($params['filename']))
  {
    $filename = $params['filename'];
    $filetype = 'text';
    $repo = 'Datafiles';

    if(!empty($params['repo']))
    {
      $repo = $params['repo'];
    }

    if(!empty($params['type']))
    {
      $filetype = $params['type'];
    }

    $cli_prefix = $smarty->getTemplateVars('CLI_PREFIX');
    $abonne = $smarty->getTemplateVars('ABONNE');

    $base_path = "/opt/fmc_repository/{$repo}/{$cli_prefix}/{$abonne}/";
    $real_path = "{$base_path}/{$filename}";
    $meta_path = "{$base_path}/.meta_{$filename}";

    // Create $base_path if it does not exist
    if (!file_exists($base_path))
    {
      mkdir_recursive($base_path, 0755);
    }

    $do_resolve_template = $smarty->getTemplateVars('DO_RESOLVE_TEMPLATE');
    if (!$do_resolve_template)
    {
      // if the call come from do_resolve_template, keep the files
      // Remove old files otherwise nsrpc create a file named ${filename}-0
      if (is_file($real_path))
      {
        unlink($real_path);
      }
      if (is_file($meta_path))
      {
        unlink($meta_path);
      }
    }

    $curr_date_long = date("U").'000';

    $add_vars['REPOSITORY'] = $repo;
    $add_vars['FILE_TYPE'] = $filetype;
    $add_vars['DATE_MODIFICATION'] = $curr_date_long;
    $add_vars['COMMENT'] = "";
    $add_vars['DATE_CREATION'] = $curr_date_long;
    $add_vars['CONFIGURATION_FILTER'] = '';
    $add_vars['TAG'] = '';
    $add_vars['TYPE'] = 'UPLOAD';

    write_hashmap_into_xmlfile($meta_path, $add_vars, $error);

    echo "> $real_path";
  }
}

function do_sendfiletorouter($params, &$smarty)
{
  if (!empty($params['filename']))
  {
    $filename = $params['filename'];
    $repo = 'Datafiles';

    if(!empty($params['repo']))
    {
      $repo = $params['repo'];
    }

    $cli_prefix = $smarty->getTemplateVars('CLI_PREFIX');
    $abonne = $smarty->getTemplateVars('ABONNE');

    echo "< /opt/fmc_repository/{$repo}/{$cli_prefix}/{$abonne}/{$filename}";
  }
}

?>