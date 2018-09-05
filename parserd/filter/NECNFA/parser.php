<?php

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('parserd', 'common.php');
require_once load_once('parserd', 'actions.php');

require_once load_once("parserd/filter/$model", 'db_tables.php');

require_once load_once("parserd/filter/$model", 'parser_snmp.php');
require_once load_once("parserd/filter/$model", 'parser_utils.php');
require_once load_once("parserd/filter/$model", 'symbols.php');

/*
  rule    => snmpTrapOID
  policy  => EventID
*/
function parse_line(&$line) {
  // originated by Saito parser (Linux_Generic)
  global $actions;
  // global $parser;
  global $fields;
  global $sdid;
  global $severity;
  global $cust_id;
  global $cust_ref;
  global $man_id;
  global $mod_id;
  global $timezone;

  $line = trim($line);

  $PDU = parse_line_snmptrap($line);
  if($PDU !== FALSE){
    sms_log_info(__FILE__.':'.__LINE__.": DEBUG: (LOG_INDEXER) ". $_SERVER['LOG_INDEXER_SERVICE'] . "\n");
    sms_log_info(__FILE__.':'.__LINE__.": DEBUG: (PDU=) ". print_r($PDU, true) . "\n");
    if($PDU['suggest']['symbolized'] === FALSE){
      // this parser does not support the OIDs
      //return parse_txt_line_no_analyze($line);
      sms_log_info(__FILE__.':'.__LINE__.': Can not find symbol name of ' . $PDU['snmpTrapOID'] . "\n");
      return false; // Fortinet seems return $false;
    }
    $fields['rule']     = $PDU['snmpTrapOID'];
    $fields['user']     = $PDU['snmp_community'];
    $fields['rawlog']   = $line;
    $fields['customer_ref'] = $cust_ref;
    $fields['customer_id'] = $cust_id;
    $fields['man_id']   = $man_id;
    $fields['mod_id']   = $mod_id;
    $fields['orig']     = $sdid;
    $fields['hostname'] = $sdid;
    $fields['Type']     = "SNMP-TRAP";
    $fields['severity'] = $PDU['suggest']['level'];
    if(isset($PDU['suggest']['date'])){
      $fields['Date']   = $PDU['suggest']['date'];
    }
    else{
      $fields['Date']   = date("Y-m-d\TH:i:sP");  // current time, NOT occured time
    }

    // Device dependent parameter
    $fields['policy']  = $PDU['varbind']['nfaEventOccurEntryName'];
    
  }
  else{  // syslog?
    /*$fields['rawlog']   = $line;
    $fields['customer_ref'] = $cust_ref;
    $fields['customer_id'] = $cust_id;
    $fields['man_id']   = $man_id;
    $fields['mod_id']   = $mod_id;
    $fields['orig']     = $sdid;
    $fields['hostname'] = $sdid;
    $fields['severity'] = $severity;
    $fields['Date']     = date("Y-m-d\TH:i:sP", substr($line, 10, 20));
    $fields['Type']     = "Syslog";*/
    // do not parse
    //return parse_txt_line_no_analyze($line);
  }
  // common action
  $dbfields['ElasticSearch'] = $fields;
  $action_name = 'Insert_into_logs';
  $action = $actions[$action_name];
  $result = new PARSER_RESULT();
  $result->ACTION = $action_name;
  $result->TYPE = $action['type'];
  $result->DB_TABLE = $action['table'];
  $result->DB_FIELDS = $dbfields;
  $result->DB_FIELDS = convert_to_database_fields($action_name, $fields);
  sms_log_info(__FILE__.':'.__LINE__.": DEBUG: (result=) ". print_r($result, true) . "\n");

  return $result;

  //return parse_txt_line_no_analyze($line);
  
}


?>

