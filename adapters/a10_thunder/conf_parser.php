<?php

// Cisco configuration parser
require_once 'smsd/sms_common.php';

/**
 * Parsed configuration are stored into an array of contexts.
 * The names of the contexts are given by the parser defintion.
 * When multiple contexts are available there is an array of contexts
 * indexed by a variable defined in the parser, else the context is stored
 * at the index 0.
 *
 * The context contains the following elements:
 *
 * context['lines'] contains the unprocessed lines of the conf (plus the first one)
 * context['parser'] contains the parser defintion of this context
 * context['values'] contains an array of variable values extracted from the conf
 */

/**
 * Function used by parser configs to get the variables from a regexp
 */
function compile_parser(&$parser) {
    // Search for the variables in the pattern
    $vars_pattern = '/\<(\w+)\>/';
    preg_match_all($vars_pattern, $parser['context-expr'], $matches);
    foreach ($matches[1] as $var) {
        $parser['vars'][] = $var;
    }
    if (! empty($parser['expr'])) {
        foreach ($parser['expr'] as $expr) {
            preg_match_all($vars_pattern, $expr, $matches);
            foreach ($matches[1] as $var) {
                $parser['vars'][] = $var;
            }
        }
    }
    if (! empty($parser['mexpr'])) {
        foreach ($parser['mexpr'] as $mexpr) {
            preg_match_all($vars_pattern, $mexpr['expr'], $matches);
            foreach ($matches[1] as $var) {
                $parser['mvars'][] = $var;
            }
        }
    }
}

// Local function used to store the variables values
function store_values(&$context, $parser, $matches) {
    if (! empty($parser['vars'])) {
        foreach ($parser['vars'] as $varname) {
            if (isset($matches[$varname])) {
                $context['values'][$varname] = $matches[$varname];
            }
        }
    }
}

/**
 * Parse a configuration into a array of contexts
 */
function parse_conf($conf, $conf_parser) {
    if (empty($conf_parser)) {
        return array();
    }

    $parsed_conf = array();

    $line = get_one_line($conf);
    while ($line !== false) {
        $next_line = false;

        // Level 0 contexts
        foreach ($conf_parser as $parser) {
            if (preg_match($parser['context-expr'], $line, $matches) > 0) {
                // The line matches change context
                if (! empty($current_parser) && ! empty($current_parser['php-var'])) {
                    if (! empty($current_parser['array-key'])) {
                        $parsed_conf[$current_parser['php-var']][trim($context['values'][$current_parser['array-key']])] = $context;
                    } else {
                        $parsed_conf[$current_parser['php-var']][0] = $context;
                    }
                }

                $current_parser = $parser;
                unset($context);
                store_values($context, $current_parser, $matches);

                // Multi part context
                if (! empty($current_parser['php-var'])) {
                    if (! empty($current_parser['array-key'])) {
                        $context = $parsed_conf[$current_parser['php-var']][trim($context['values'][$current_parser['array-key']])];
                    } else {
                        $context = $parsed_conf[$current_parser['php-var']][0];
                    }
                }
                // Context must have changed
                store_values($context, $current_parser, $matches);

                // Store the first line if not yet done
                if (empty($context['lines'])) {
                    $context['lines'][0] = $line;
                }

                $context['parser'] = $current_parser;

                $next_line = true;
                break;
            }
        }

        if ($next_line) {
            $line = get_one_line($conf);
            continue;
        }

        // Check ignored lines
        if (! empty($current_parser['ignore-expr'])) {
            foreach ($current_parser['ignore-expr'] as $expr) {
                if (preg_match($expr, $line, $matches)) {
                    $next_line = true;
                    break;
                }
            }
        }

        if ($next_line) {
            $line = get_one_line($conf);
            continue;
        }

        // Store lines not matching with a regexp
        if (! empty($current_parser['php-var'])) {
            $trimmed = trim($line);
            if ((strpos($trimmed, '!') !== 0) && (strlen($trimmed) !== 0)) {
                $context['lines'][] = $line;
            }
        }

        // Within current context
        if (! empty($current_parser['expr'])) {
            foreach ($current_parser['expr'] as $expr) {
                if (preg_match($expr, $line, $matches)) {
                    store_values($context, $current_parser, $matches);
                    $next_line = true;
                    break;
                }
            }
        }

        if ($next_line) {
            $line = get_one_line($conf);
            continue;
        }

        // multiple lines
        if (! empty($current_parser['mexpr'])) {
            foreach ($current_parser['mexpr'] as $mexpr) {
                if (preg_match($mexpr['expr'], $line, $matches)) {
                    $key = $matches[$mexpr['array_key']];
                    if (isset($key)) {
                        foreach ($current_parser['mvars'] as $varname) {
                            if (isset($matches[$varname])) {
                                $context['mvalues'][$varname][$key] = $matches[$varname];
                            }
                        }
                    }
                    $next_line = true;
                    break;
                }
            }
        }

        $line = get_one_line($conf);
    }

    // The last context
    if (! empty($current_parser) && ! empty($current_parser['php-var'])) {
        if (! empty($current_parser['array-key'])) {
            $parsed_conf[$current_parser['php-var']][$context['values'][$current_parser['array-key']]] = $context;
        } else {
            $parsed_conf[$current_parser['php-var']][0] = $context;
        }
    }

    return $parsed_conf;
}

// Internal
function prepare($line) {
    return preg_replace('/\s\s+/', ' ', trim($line));
}

/**
 * Compute the delta between the previous (running) configuration
 * and the next (generated) configuration in order to apply only the
 * minimum of changes.
 * The return is an array of 3 arrays contexts.
 *
 * $diff['remove'] contains the contexts to remove
 * $diff['add'] contains the contexts to add to the configuration
 * $diff['update'] contains the contexts to update
 *
 * The contexts to update contain 2 arrays:
 *
 * context['removed_lines'] containing the lines to remove from the conf
 * context['added_lines'] containing the lines to add to the conf
 */
function conf_differ($prev_conf, $next_conf) {
    $to_remove = array();
    $to_add = array();
    $to_update = array();

    // Search the previous conf first
    if (! empty($prev_conf)) {
        foreach ($prev_conf as $key => $context) {
            foreach ($context as $sub_key => $prev_context) {
                $next_context = @$next_conf[$key][$sub_key];
                if (empty($next_context)) {
                    if (empty($prev_context['parser']['clean-ignore'])) {
                        $to_remove[$key][$sub_key] = $prev_context;
                    }
                } else {
                    $added_lines = array();
                    $removed_lines = array();

                    // Compare context lines
                    foreach ($prev_context['lines'] as $prev_line) {
                        $found = false;
                        $prev_line = prepare($prev_line);
                        foreach ($next_context['lines'] as $linekey => $next_line) {
                            $next_line = prepare($next_line);
                            // Manage forced lines
                            $clean = true;
                            $forced_lines = @$prev_context['parser']['forced'];
                            if (! empty($forced_lines)) {
                                foreach ($forced_lines as $forced_line_expr) {
                                    if (preg_match($forced_line_expr, $next_line, $matches)) {
                                        $clean = false;
                                        break;
                                    }
                                }
                            }
                            if (strcmp($prev_line, $next_line) === 0) {
                                // Same line
                                if ($clean) {
                                    unset($next_context['lines'][$linekey]);
                                }
                                $found = true;
                                break;
                            }
                        }
                        if (! $found) {
                            // Check lines with no-remove list
                            $parser = $next_context['parser'];
                            $no_remove_list = $parser['no-remove'];
                            $remove = true;
                            if (! empty($no_remove_list)) {
                                foreach ($no_remove_list as $no_remove_expr) {
                                    if (preg_match($no_remove_expr, $prev_line, $matches)) {
                                        $remove = false;
                                        break;
                                    }
                                }
                            }
                            if ($remove) {
                                $removed_lines[] = $prev_line;
                            }
                        }
                    }
                    foreach ($next_context['lines'] as $next_line) {
                        $added_lines[] = $next_line;
                    }

                    if (! empty($added_lines) || ! empty($removed_lines)) {
                        $prev_context['added_lines'] = $added_lines;
                        $prev_context['removed_lines'] = $removed_lines;
                        $to_update[$key][$sub_key] = $prev_context;
                    }

                    // remove contexts already compared
                    unset($next_conf[$key][$sub_key]);
                }
            }
        }
    }

    // Contexts to add are the remaining contexts in next config
    if (! empty($next_conf)) {
        foreach ($next_conf as $key => $context) {
            foreach ($context as $sub_key => $next_context) {
                $to_add[$key][$sub_key] = $next_context;
            }
        }
    }

    $diff['add'] = $to_add;
    $diff['remove'] = $to_remove;
    $diff['update'] = $to_update;

    return $diff;
}

function generate_conf_from_diff($diff_conf, $conf_parser) {
    /*
     * The configuration is generated using the following steps:
     *
     * 1- remove the lines of updated sections, and remove the sections
     * 2- add the lines of updated sections and the new sections
     *
     * The order within each step, is given by the parser array order.
     * The remove step is done in the reverse order.
     *
     * Be careful to the order in the parser definition !
     */
    $configuration = '';
    $index = count($conf_parser);
    $index --;
    while ($index >= 0) {
        $parser = $conf_parser[$index];
        $index --;
        $key = $parser['php-var'];

        // Remove the references within the remaining objects
        if (! empty($diff_conf['update'][$key])) {
            $context_list = $diff_conf['update'][$key];
            $configuration .= "!\n";
            foreach ($context_list as $context) {
                if (! empty($context['removed_lines'])) {
                    // Enter context
                    $configuration .= "{$context['lines'][0]}\n";
                    // Remove lines
                    foreach ($context['removed_lines'] as $line) {
                        if (strpos(trim($line), 'no ') === 0) {
                            $configuration .= " ";
                            $configuration .= substr(trim($line), 3);
                            $configuration .= "\n";
                        } else {
                            $remove_done = false;
                            if (! empty($parser['remove-line'])) {
                                foreach ($parser['remove-line'] as $remove_line) {
                                    if (preg_match($remove_line['expr'], $line, $matches) > 0) {
                                        $configuration .= " {$remove_line['cmd']}\n";
                                        $remove_done = true;
                                        break;
                                    }
                                }
                            }
                            if (! $remove_done) {
                                $configuration .= " no $line\n";
                            }
                        }
                    }
                    // Exit context
                    $end_line = $context['parser']['end-line'];
                    if (! empty($end_line)) {
                        $configuration .= "$end_line\n";
                    }
                }
            }
        }

        // remove the unnecessary objects
        if (! empty($diff_conf['remove'][$key])) {
            $context_list = $diff_conf['remove'][$key];
            $configuration .= "!\n";
            foreach ($context_list as $context) {
                $line = $context['lines'][0];
                if (! empty($context['parser']['remove'])) {
                    $values = $context['values'];
                    eval("\$str = \"{$context['parser']['remove']}\";");
                    $configuration .= "$str\n";
                } else {
                    if (strpos(trim($line), 'no ') === 0) {
                        $configuration .= " ";
                        $configuration .= substr(trim($line), 3);
                        $configuration .= "\n";
                    } else {
                        $configuration .= "no $line\n";
                    }
                }
            }
        }
    }

    if (! empty($conf_parser)) {
        foreach ($conf_parser as $parser) {
            $key = $parser['php-var'];

            // add the new objects
            if (! empty($diff_conf['add'][$key])) {
                $context_list = $diff_conf['add'][$key];
                $configuration .= "!\n";
                foreach ($context_list as $context) {
                    foreach ($context['lines'] as $line) {
                        $configuration .= "$line\n";
                    }
                    // Exit context
                    $end_line = $context['parser']['end-line'];
                    if (! empty($end_line)) {
                        $configuration .= "$end_line\n";
                    }
                }
            }

            // Add the references to the new objects
            if (! empty($diff_conf['update'][$key])) {
                $context_list = $diff_conf['update'][$key];
                $configuration .= "!\n";
                foreach ($context_list as $context) {
                    if (! empty($context['added_lines'])) {
                        // Enter context
                        $configuration .= "{$context['lines'][0]}\n";
                        // Add lines
                        foreach ($context['added_lines'] as $line) {
                            $configuration .= "$line\n";
                        }
                        // Exit context
                        $end_line = $context['parser']['end-line'];
                        if (! empty($end_line)) {
                            $configuration .= "$end_line\n";
                        }
                    }
                }
            }
        }
    }
    return $configuration;
}

function dump_parse($parsed_conf) {
    $buffer = '';

    foreach ($parsed_conf as $php_var => $parsed_list) {
        // $buffer .= "\n$php_var\n";
        foreach ($parsed_list as $parsed_context) {
            foreach ($parsed_context['lines'] as $line) {
                $buffer .= "$line\n";
            }
        }
    }
    return $buffer;
}

function dump_delta($delta_conf) {
    $buffer = '';

    if (! empty($delta_conf['add'])) {
        foreach ($delta_conf['add'] as $context_list) {
            foreach ($context_list as $context) {
                $buffer .= "\n";
                foreach ($context['lines'] as $line) {
                    $buffer .= "+ $line\n";
                }
            }
        }
    }

    if (! empty($delta_conf['remove'])) {
        foreach ($delta_conf['remove'] as $context_list) {
            $buffer .= "\n";
            foreach ($context_list as $context) {
                foreach ($context['lines'] as $line) {
                    $buffer .= "- $line\n";
                }
            }
        }
    }

    if (! empty($delta_conf['update'])) {
        foreach ($delta_conf['update'] as $context_list) {
            $buffer .= "\n";
            foreach ($context_list as $context) {
                $buffer .= "= {$context['lines'][0]}\n";
                if (! empty($context['added_lines'])) {
                    foreach ($context['added_lines'] as $line) {
                        $buffer .= "+ $line\n";
                    }
                }
                if (! empty($context['removed_lines'])) {
                    foreach ($context['removed_lines'] as $line) {
                        $buffer .= "-  $line\n";
                    }
                }
            }
        }
    }
    return $buffer;
}

?>