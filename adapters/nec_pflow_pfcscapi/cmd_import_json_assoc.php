<?php
/*
 * Created: November 13, 2017
 * Derived smsd/cmd_import_json.php to set the $assoc parameter of json_decode to true 
 *   which is used as a parameter to arrayToXml.
 */
 
require_once 'smsd/cmd_import_json.php';

class cmd_import_associative extends cmd_import
{
  /**
   * Parse a configuration using the defined parsers
   * @param JSON $buffer configuration to parse
   * @param unknown_type $objects	objects retrieved
   */
  function parse($jbuffer, &$objects)
  {
    sms_log_debug(15, "JSON string to be parsed: " . $jbuffer);
    //$buffer = arrayToXml(json_decode($jbuffer), '<api></api>');
    $buffer = arrayToXml(json_decode($jbuffer, true), '<api></api>');
    sms_log_debug(15, "Converted XML: " . $buffer->asXml());

    $sections_xml = $buffer->xpath($this->parser['section']['xpath']);
    if (empty($objects[$this->object_name]))
    {
      $objects[$this->object_name] = array();
    }
    $ref = &$objects[$this->object_name];

    foreach ($sections_xml as $section_xml)
    {
      // Object instance to parse
      $instance_xml = new SimpleXMLElement($section_xml->asXml());
      $instance = $this->parse_lines($instance_xml, $this->parser['lines']);
      $object_id = $instance['object_id'];
      if (!isset($object_id))
      {
        if ($this->is_singleton)
        {
          $object_id = '0';
        }
        else
        {
          $object_id = md5($section_xml->asXml());
        }
        $instance['object_id'] = $object_id;
      }
      $object_id = str_replace('.', '_', $object_id);
      $ref[$object_id] = $instance;
    }
    $post_manager = new post_import_manager();
    $post_manager->manage($this->object_name, $this->post_template, $objects);
  }
}

?>
