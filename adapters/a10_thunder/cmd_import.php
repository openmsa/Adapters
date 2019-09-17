<?php
/*
 * Version: $Id: cmd_import.php 50 2016-07-07 14:05:47Z domeki $ Created: Apr 28, 2011
 * Available global variables:
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';
require_once load_once('smsd', 'post_import_manager.php');

/*
 * Each line is parsed alone and is matched against both the section and lines regexps.
 */
class cmd_import {
    var $object_name;
    // CLI operation to get the configuration to parse
    var $operation;
    /*
     * Parser composed of:
     * section[]
     * lines
     * ..line[]
     * ....array_name
     * ....regexp[]
     * ....mregexp[]
     * ....lines
     * ..ignore[]
     * ....regexp[]
     */
    var $parser;
    var $post_template;

    /**
     * IMPORT PARSER
     *
     * @param simpleXML $command
     *            IMPORT XML definition
     */
    function __construct($object, $command, $json_params = null) {
        $this->json_params = $json_params;
        $this->operation = $command->operation;
        $this->parser = array();
        if (empty($command->parser)) {
            echo "IMPORT parser syntax error : missing 'section' part\n";
            return;
        }
        $this->object_name = $object;
        foreach ($command->parser->section->regexp as $regexp) {
            $this->parser['section'][] = trim("{$regexp}");
        }
        $this->parser['lines'] = $this->read_lines($command->parser->lines);
        $this->post_template = $command->post_template;
    }

    /**
     * Parse XML <lines> tag
     *
     * @param simpleXML $lines
     *            <lines> node
     * @return an array of the parsers lines
     */
    function read_lines($lines) {
        $parser = array();

        // Read ignore patterns
        if (! empty($lines->ignore)) {
            foreach ($lines->ignore as $ignore) {
                $ignore_parsers = array();
                foreach ($ignore->regexp as $regexp) {
                    $ignore_parsers['regexp'][] = trim($regexp);
                }
                $parser['ignore'][] = $ignore_parsers;
            }
        }
        // Read line patterns
        if (! empty($lines->line)) {
            foreach ($lines->line as $line) {
                $parser['line'][] = $this->read_line($line);
            }
        }
        return $parser;
    }

    /**
     * Read XML line in the definition
     *
     * @param unknown $line
     * @return multitype:string Ambigous <an, multitype:, multitype:string an >
     */
    function read_line($line) {
        $parser = array();

        if (! empty($line->array)) {
            $parser['array_name'] = (string) $line->array['name'];
            $line = $line->array;
        }

        foreach ($line->regexp as $regexp) {
            if (! empty($regexp['type']) && ($regexp['type'] == 'multiple')) {
                $parser['mregexp'][] = trim($regexp);
            } else {
                $parser['regexp'][] = trim($regexp);
            }
        }

        foreach ($line->mregexp as $mregexp) {
            $parser['mregexp'][] = trim($mregexp);
        }

        if (! empty($line->lines)) {
            $parser['lines'] = $this->read_lines($line->lines);
        }

        return $parser;
    }

    /**
     * Parse a configuration using the defined parsers
     *
     * @param unknown_type $buffer
     *            to parse
     * @param unknown_type $objects
     *            retrieved
     */
    function parse($buffer, &$objects) {
        echo "Buffer to parse:\n $buffer\n";

        $var_pattern = '/\(\?\<(\w+)\>[^<]+\)/';
        $config = $buffer;
        $line = get_one_line($config);

        while ($line !== false) {
            // Check the section regexp
            if (! empty($this->parser['section'])) {
                foreach ($this->parser['section'] as $regexp) {
                    if (regexp_match("Object template {$this->object_name}", $regexp, $line, $matches) > 0) {
                        if (! empty($matches['object_id'])) {
                            // $object_id = $matches['object_id'];
                            $object_id = str_replace('.', '_', $matches['object_id']);
                        } else {
                            $object_id = '0';
                        }
                        if (empty($objects[$this->object_name])) {
                            $objects[$this->object_name] = array();
                        }
                        // reference object
                        $ref = &$objects[$this->object_name];
                        if (empty($ref[$object_id])) {
                            $ref[$object_id] = array();
                        }
                        // instance of the object
                        $object = &$ref[$object_id];
                        // Variables can be affected on sections
                        if (regexp_match_all("Object template {$this->object_name}", $var_pattern, $regexp, $vars) > 0) {
                            foreach ($vars[1] as $var_name) {
                                $object[$var_name] = $matches[$var_name];
                            }
                        }
                        break;
                    }
                }
            }

            // Parse the line
            $this->parse_lines($line, $this->parser['lines'], $config, $object);
            $line = get_one_line($config);
        }
        $post_manager = new post_import_manager();
        $post_manager->manage($this->object_name, $this->post_template, $objects);
    }

    function parse_lines(&$theline, $parser, &$config, &$object) {
        $var_pattern = '/\(\?\<(\w+)\>[^<]+\)/';
        $found = false;
        $line = trim($theline);

        // Check the accepted lines
        $rule_found = false;
        if (! empty($parser['line'])) {
            // iterate the parsers
            foreach ($parser['line'] as $line_parser) {
                if (! empty($line_parser)) {
                    unset($array_name);
                    $found = false;
                    unset($sub_object);
                    $sub_object = array();
                    // iterate the rules (array, regexp, mregexp, lines)
                    foreach ($line_parser as $rule => $rule_content) {
                        if ($rule === 'array_name') {
                            // get the name of the array (the <index> in the regexp will give the array index)
                            $array_name = $rule_content;
                        } else
                            if ($rule === 'regexp') {
                                foreach ($rule_content as $regexp) {
                                    if (regexp_match("Object template {$this->object_name}", $regexp, $line, $matches) > 0) {
                                        $found = true;
                                        // the line matches, get the variables (if no variables do nothing)
                                        if (regexp_match_all("Object template {$this->object_name}", $var_pattern, $regexp, $vars) > 0) {
                                            if (! empty($matches['name'])) {
                                                // name/value are reserved keywords
                                                $name = $matches['name'];
                                                $sub_object[$name] = $matches['value'];
                                            } else {
                                                foreach ($vars[1] as $var_name) {
                                                    if (! empty($matches[$var_name])) {
                                                        $sub_object[$var_name] = $matches[$var_name];
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            } else
                                if ($rule === 'mregexp') {
                                    foreach ($rule_content as $regexp) {
                                        if (regexp_match_all("Object template {$this->object_name}", $regexp, $line, $matches) < 1) {
                                            // NO MATCH -> next parser
                                            $found = false;
                                            break 2;
                                        }
                                        $found = true;

                                        // the line matches, get the variables (if no variables do nothing)
                                        if (regexp_match_all("Object template {$this->object_name}", $var_pattern, $regexp, $vars) > 0) {
                                            foreach ($vars[1] as $var_name) {
                                                $sub_object[$var_name] = $matches[$var_name];
                                            }
                                        }
                                    }
                                } else
                                    if ($rule === 'lines') {
                                        // store the result for later
                                        $lines = $rule_content;
                                    }
                    }

                    // Everything matches for the line parser
                    if ($found) {
                        $rule_found = true;
                        // Store the line as a set of variables in the object
                        if (empty($array_name)) {
                            foreach ($sub_object as $var_name => $value) {
                                $object[$var_name] = $value;
                            }
                            $obj_anchor = &$object;
                        } else {
                            // array
                            reset($sub_object);
                            // In the case of mregexp, the use of preg_match_all generate arrays
                            // for the variables.
                            $is_multiple = is_array(current($sub_object));

                            if (empty($object[$array_name])) {
                                $object[$array_name] = array();
                            }
                            $objarray = &$object[$array_name];

                            if (! $is_multiple) {
                                if (empty($sub_object['index'])) {
                                    $objarray[] = &$sub_object;
                                    $obj_anchor = &$sub_object;
                                } else {
                                    $index = $sub_object['index'];
                                    if (is_array($objarray[$index])) {
                                        $arr = array_merge($objarray[$index], $sub_object);
                                        $objarray[$index] = $arr;
                                    } else {
                                        $objarray[$index] = $sub_object;
                                    }
                                    $obj_anchor = &$objarray[$index];
                                }
                            } else {
                                if (empty($sub_object['index'])) {
                                    $tmp = current($sub_object);
                                    $vi = 0;
                                    foreach ($tmp as $i) {
                                        foreach ($sub_object as $var_name => $value) {
                                            $objarray[][$var_name] = $value[$vi];
                                        }
                                        $vi = $vi + 1;
                                    }
                                } else {
                                    $vi = 0;
                                    foreach ($sub_object['index'] as $i) {
                                        $index = $i;
                                        foreach ($sub_object as $var_name => $value) {
                                            $objarray[$index][$var_name] = $value[$vi];
                                        }
                                        $vi = $vi + 1;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (! $rule_found) {
            // Check the ignored lines
            if (! empty($parser['ignore'])) {
                foreach ($parser['ignore'] as $ignore_line) {
                    foreach ($ignore_line['regexp'] as $regexp) {
                        if (regexp_match("Object template {$this->object_name}", $regexp, $line, $matches) > 0) {
                            // Ignored line, continue to parse with the next line
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        if (! empty($lines) && ! empty($obj_anchor)) {
            // Sub-variable case
            // The following config lines will be parsed with the sub-parser
            $line = get_one_line($config);
            while (($line !== false) && $this->parse_lines($line, $lines, $config, $obj_anchor)) {
                $line = get_one_line($config);
            }
        }

        return true;
    }

    function eval_operation() {
        global $sdid;
        global $sms_smarty_template;

        $name = md5($this->operation);
        $sms_smarty_template[$name] = $this->operation;

        $op = '';

        if (! empty($this->json_params)) {
            foreach ($this->json_params as $object_id => $value) {
                // Remove dots into object_id variable
                if (is_array($value) && ! isset($value['object_id'])) {
                    $value['object_id'] = $object_id;
                }
                $params['object_id'] = str_replace('.', '_', $object_id);
                $params['params'] = $value;
                $op .= resolve_template_in_var($sdid, $name, $params);
            }
        } else {
            $op .= resolve_template_in_var($sdid, $name, null);
        }

        return (string) $op;
    }
}

?>
