<?php
/*
 * Version: $Id$
 * Created: May 30, 2011
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Device adaptor
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('esa', 'esa_connect.php');
require_once load_once('esa', 'esa_apply_conf.php');
require_once load_once('esa', 'esa_configuration.php');

require_once "$db_objects";
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
  if (E_RECOVERABLE_ERROR === $errno)
  {
    echo "'catched' catchable fatal error\n";
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
  }
  return false;
}

set_error_handler('myErrorHandler');

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null, $adminpasswd = null)
{
  $ret = esa_connect($login, $passwd);
  
  return $ret;
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect($clean_exit = false)
{
  $ret = esa_disconnect();
  
  return $ret;
}
function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from)
{
  $toDom = dom_import_simplexml($to);
  $fromDom = dom_import_simplexml($from);
  $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
}
function sxml_insert_before(SimpleXMLElement $following_element, SimpleXMLElement $content)
{
  $DOMNode_content = dom_import_simplexml($content);
  $DOMNode_following_element = dom_import_simplexml($following_element);
  $DOMNode_following_element->parentNode->insertBefore($DOMNode_following_element->ownerDocument->importNode($DOMNode_content, true), $DOMNode_following_element);
}
function sxml_insert_after(SimpleXMLElement $previous_element, SimpleXMLElement $content)
{
  $DOMNode_content = dom_import_simplexml($content);
  $DOMNode_previous_element = dom_import_simplexml($previous_element);
  $DOMNode_previous_element->parentNode->insertBefore($DOMNode_previous_element->ownerDocument->importNode($DOMNode_content, true), $DOMNode_previous_element->nextSibling);
}
function sxml_replace(SimpleXMLElement $to, SimpleXMLElement $from)
{
  $toDom = dom_import_simplexml($to);
  $fromDom = dom_import_simplexml($from);
  $toDom->parentNode->replaceChild($toDom->ownerDocument->importNode($fromDom, true), $toDom);
}
function sxml_remove(SimpleXMLElement $to)
{
  $toDom = dom_import_simplexml($to);
  $toDom->parentNode->removeChild($toDom);
}

/**
 * Apply a configuration buffer to a device
 * @param  $configuration
 * @param  $need_sd_connection
 */
function sd_apply_conf($configuration, $need_sd_connection = false)
{
  if ($need_sd_connection)
  {
    sd_connect();
  }
  
  /* Retrieve actual device configuration */
  $conf = new esa_configuration($sdid);
  
  /* Apply command definition lines to generate a new configuration xml tree */
  $config_to_apply = $conf->get_running_conf();
  
  /* Configurations lines to apply is in $config_to_apply: */
  $xmlconfig = simplexml_load_string(preg_replace('/xmlns="[^"]+"/', '', $config_to_apply));
  
  // Split configuration by lines begining with action:
  $vconfig = explode("\naction=", $configuration);
  foreach ($vconfig as $line)
  {
    $line = trim($line);
    if (!empty($line))
    {
      if (0 !== strpos($line, "action="))
      {
        $line = "action=" . $line;
      }
      $xpr_action = "";
      $xpr_xpath = "";
      $xpr_element = "";
      $content_to_adapt = "";
      $vaction;
      $vxpath;
      $velement;
      
      $pattern_action = '/^action=([^\&]+)\&xpath=([^\&]+)\&element=(.*)/s';
      $preg_match_status = preg_match($pattern_action, $line, $matches);
      if ($preg_match_status === 1)
      {
        $xpr_action = $matches[1];
        $xpr_xpath = $matches[2];
        $xpr_element = $matches[3];
        
        $vxpath = explode("##", $xpr_xpath);
        $velement = explode("##", $xpr_element);
        $vaction = explode("##", $xpr_action);
        
        echo "preg_match succeed\n";
        echo "action : " . $xpr_action . "\n";
        echo "xpath  : " . $xpr_xpath . "\n";
        echo "element: " . $xpr_element . "\n";
      }
      else
      {
        $pattern_action = '/^action=([^\&]+)\&xpath=([^\&]+)/s';
        $preg_match_status = preg_match($pattern_action, $line, $matches);
        if ($preg_match_status === 1)
        {
          $xpr_action = $matches[1];
          $xpr_xpath = $matches[2];
          $vxpath = explode("##", $xpr_xpath);
          $vaction = explode("##", $xpr_action);
          
          echo "preg_match succedd\n";
          echo "action : " . $xpr_action . "\n";
          echo "xpath  : " . $xpr_xpath . "\n";
        }
        else
        {
          echo "preg_match failure on $line\n";
          return ERR_SD_CMDFAILED;
        }
      }
      
      // Split the xpath in case we want to manage severall part:
      foreach ($vxpath as $vkey => $subobjectxpathit)
      {
        $subobjectxpath = $subobjectxpathit;
        unset($xpath_before);
        unset($xpath_after);
        
        // Take care, the xpath may contain the '|' char to separate the insertBefore xpath.
        $ordered_schema = explode("|", trim($subobjectxpath));
        if (strpos($subobjectxpath, 'InsertBefore') !== false)
        {
          $subobjectxpath = $ordered_schema[0];
          $xpath_before = $ordered_schema[2];
        }
        elseif (strpos($subobjectxpath, 'InsertAfter') !== false)
        {
          $subobjectxpath = $ordered_schema[0];
          $xpath_after = $ordered_schema[2];
        }
        
        $matched_branch = $xmlconfig->xpath(trim($subobjectxpath));
        if ($matched_branch === FALSE)
        {
          echo __FILE__ . ':' . __LINE__ . "  xpath (" . trim($subobjectxpath) . ") failed\n";
        }
        else
        {
          while (list (, $node) = each($matched_branch))
          {
            echo "a branch found for " . trim($subobjectxpath) . "!\n";
            
            if (trim($vaction[$vkey]) === "set")
            {
              $element_content = trim($velement[$vkey]);
              $content_to_adapt = simplexml_load_string($element_content);
              if ($content_to_adapt === FALSE)
              {
                $element_content = "<ubiqubevector>" . trim($velement[$vkey]) . "</ubiqubevector>";
                $content_to_adapt = simplexml_load_string($element_content);
                if ($content_to_adapt === FALSE)
                {
                  echo "simplexml_load_string failed on $element_content\n";
                  return ERR_SD_CMDFAILED;
                }
                
                foreach ($content_to_adapt->children() as $child_to_add)
                {
                  sxml_append($node, $child_to_add);
                }
              }
              else
              {
                sxml_append($node, $content_to_adapt);
              }
            }
            
            if (trim($vaction[$vkey]) === "edit")
            {
							$tmp = trim($velement[$vkey]);
              $content_to_adapt = simplexml_load_string(trim($velement[$vkey]));
              
              if (isset($xpath_before))
              {
                sxml_remove($node);
                $matched_node_before = $xmlconfig->xpath(trim($xpath_before));
                if ($matched_node_before === FALSE)
                {
                  throw new Exception(__FILE__ . ':' . __LINE__ . '  xpath (' . trim($xpath_before) . ') failed.');
                }
                while (list (, $node_before) = each($matched_node_before))
                {
                  sxml_insert_before($node_before, $content_to_adapt);
                }
              }
              elseif (isset($xpath_after))
              {
                sxml_remove($node);
                $matched_node_after = $xmlconfig->xpath(trim($xpath_after));
                if ($matched_node_after === FALSE)
                {
                  throw new Exception(__FILE__ . ':' . __LINE__ . '  xpath (' . trim($xpath_after) . ') failed.');
                }
                while (list (, $node_after) = each($matched_node_after))
                {
                  sxml_insert_after($node_after, $content_to_adapt);
                }
              }
              else
              {
                sxml_replace($node, $content_to_adapt);
              }
            }
            
            if (trim($vaction[$vkey]) === "delete")
            {
              sxml_remove($node);
            }
          }
        }
      }
    }
    $line = get_one_line($configuration);
  }
  
  $ret = esa_apply_conf($xmlconfig->asXML(), false);
  
  if ($need_sd_connection)
  {
    sd_disconnect();
  }
  
  return $ret;
}

/**
 * Execute a command on a device
 * @param  $cmd
 * @param  $need_sd_connection
 */
function sd_execute_command($cmd, $need_sd_connection = false)
{
  global $sms_sd_ctx;
  
  if ($need_sd_connection)
  {
    $ret = sd_connect();
    if ($ret !== SMS_OK)
    {
      return false;
    }
  }
  
  $ret = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd);
  
  if ($need_sd_connection)
  {
    sd_disconnect(true);
  }
  
  return $ret;
}

?>
