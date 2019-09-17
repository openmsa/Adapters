<?php

require_once load_once("parserd/filter/$model", 'parser_utils.php');
require_once load_once("parserd/filter/$model", 'symbols.php');


/**
 * parse_line_snmptrap
 * returns SNMP commonized PDU if log is SNMPTRAP, otherwise returns FALSE
 * @param $line MSO parser line
 * @param $symbol_style if TRUE or 'symbol', automatically convert OID numbers to symbol name, otherwise do nothing
 * @return mixed $PDU (array or FALSE)
 * @note $PDU array contains following variables:
 * @note $PDU['snmp_version']: SNMP version (1,2,3)
 * @note $PUD['snmp_community']: SNMP community name
 * @note $PDU['sysUpTimeInstance']: sysUptime in SNMP Trap (not available in SNMPv1)
 * @note $PDU['snmpTrapOID']: Trap enterprise OID
 * @note $PDU['varbind']: array of (OID => VALUE)
 * @note e.g.) $PDU['varbind']['1.3.6.1.2.1.2.2.1.1'] = 1
 * @note e.g.) $PDU['varbind']['ifIndex'] = 1
 * @note $PDU['suggest']: array of some parameter suggestion
 * @note $PUD['suggest']['date']: suggest date when event occurs
 * @note $PUD['suggest']['level']: suggest severity of this trap (0-7)
 * @note $PDU['symbol']: CURRENTLY NOT SUPPORTED: array of (OID(number) => Symbol name and/or symbol name => OID)
*/
function parse_line_snmptrap($line, $symbol_style = 'symbol')
{
  $PDU   = array();

  global $symbol;
  global $date_type;

  echo "DEBUG: parse_line_snmptrap start\n";

  if(preg_match('/(?P<vnoc>\<[[:digit:]]+\>%VNOC-(?P<severity>[[:digit:]])-TrapSnmp\([[:digit:]]+\): )(?P<line>.*)/', $line, $matches) > 0){
    $is_snmp  = $matches['vnoc'];
    $linebody = $matches['line'];
    $severity = $matches['severity'];

    // analyze line --- parse by white space
    $array = my_getcsv($linebody, " ", '"', "\\");
    
    $snmp_item = array();
    foreach ($array as $value){
      $tmp = my_getcsv($value, "=", '"', "\\");   // split A=B style format (consider delimiter is '=', and remove all enclosure)
      $snmp_key             = $tmp[0];
      $snmp_value           = $tmp[1];
      $snmp_item[$snmp_key] = $snmp_value;
    }

    // Construct PDU data
    foreach($snmp_item as $key => $value){
      switch($key){
        case "V": $PDU['snmp_version']   = $value; break;
        case "C": $PDU['snmp_community'] = $value; break;
        case "sysUpTimeInstance": $PDU['sysUpTimeInstance'] = $value; break;
        //case "timestamp": $PDU['timestamp'] = $value; break;
        case "snmpTrapOID": $PDU['snmpTrapOID'] = $value; break;
        default: $PDU['varbind'][$key] = $value; break;   // TENUKI? - if other parameter added to 'rawlog', it will implicitly add to this array
      }  //switch
    }    // foreach

    // find date type in PDU
    $date_to_set = '';
    foreach($date_type as $oid => $type_string){
      if(isset($PDU['varbind'][$oid])){
        $date_candidate = $PDU['varbind'][$oid];
        switch($type_string){
          case 'string':
            $date_candidate = str_replace(array("(", ")"), "", $date_candidate);  // remove "(" ")"
            break;
          case 'dateandtime':
            // sorry I don't know that how MSO constructs rawlog of OCTET-STRING.
            // this code assumes that is 16 or 22 of hexadeciaml digit without white spaces
            $date_candidate = convert_dateandtime_hex_to_string($date_candidate);
            break;
          default:
            // Oops!
            // if no date field included,
            // currently not suggests time information.
            //$date_candidate = date("Y-m-d\TH:i:sP");
        } // switch

        if(!empty($date_candidate)){
          //Note: currently, multiple 'date' found in varbind, the final one will be selected.
          //Note: PHP's array holds their order, so if lowest priority of $date_type to be set first, 
          //      and highest priority of $date_type to be set finally, this code works good.
          $date_to_set = date("Y-m-d\TH:i:sP", strtotime($date_candidate));
        }
      }   // if($PDU[varbind][oid])
    }     // foreach 

    // Construct symbol list
    $is_symbolized = FALSE;
    if(!empty($symbol[$PDU['snmpTrapOID']])){ 
      $PDU['symbol'][$PDU['snmpTrapOID']] = $symbol[$PDU['snmpTrapOID']];
      if ($symbol_style === 'symbol' || $symbol_style === TRUE){
        $is_symbolized      = TRUE;
        $PDU['snmpTrapOID'] = $symbol[$PDU['snmpTrapOID']];  // and convert trap OID
      }
    }
    foreach($PDU['varbind'] as $key => $value){
      if(!empty($symbol[$key])){
        if ($symbol_style === 'symbol' || $symbol_style === TRUE){
          $PDU['varbind'][$symbol[$key]] = $PDU['varbind'][$key];  // convert varbind OID
          unset ($PDU['varbind'][$key]);  // and remove from varbind array
        }
        $PDU['symbol'][$key] = $symbol[$key];
      }
    }

    // Construct and add suggestion data to PDU
    $PDU['suggest']['date']  = $date_to_set;
    $PDU['suggest']['level'] = $severity;
    $PDU['suggest']['symbolized'] = $is_symbolized;

    return $PDU;
  }
  else{
    return FALSE;
  }
}
