<?php

// $symbol OID number => symbol name
$symbol['1.3.6.1.4.1.119.2.3.239.2.0.1'] = 'nfaTrafficThreshExceeded';
$symbol['1.3.6.1.4.1.119.2.3.239.1.1'] = 'nfaEventOccurTime';
$symbol['1.3.6.1.4.1.119.2.3.239.1.2'] = 'nfaEventOccurExpAddr';
$symbol['1.3.6.1.4.1.119.2.3.239.1.3'] = 'nfaEventOccurExpIfIndex';
$symbol['1.3.6.1.4.1.119.2.3.239.1.4'] = 'nfaEventOccurExpName';
$symbol['1.3.6.1.4.1.119.2.3.239.1.5'] = 'nfaEventOccurExpIfName';
$symbol['1.3.6.1.4.1.119.2.3.239.1.6'] = 'nfaEventOccurEntryName';
$symbol['1.3.6.1.4.1.119.2.3.239.1.7'] = 'nfaEventLevel';
$symbol['1.3.6.1.4.1.119.2.3.239.1.8'] = 'nfaThreshFlowConditions';
$symbol['1.3.6.1.4.1.119.2.3.239.1.9'] = 'nfaThreshConfData';
$symbol['1.3.6.1.4.1.119.2.3.239.1.10'] = 'nfaThreshConfTimes';
$symbol['1.3.6.1.4.1.119.2.3.239.1.11'] = 'nfaThreshConfUnit';
$symbol['1.3.6.1.4.1.119.2.3.239.1.12'] = 'nfaThreshMeasuredData';
$symbol['1.3.6.1.4.1.119.2.3.239.2.0.2'] = 'nfaTrafficThreshCleared';
$symbol['1.3.6.1.4.1.119.2.3.239.2.0.3'] = 'nfaIfUsageThreshExceeded';
$symbol['1.3.6.1.4.1.119.2.3.239.2.0.4'] = 'nfaIfUsageThreshCleared';

// OIDs which includes "date" and to discovers its type
// $date_type[$OID] = 'string' | 'dateandtime' // | 'integer' | 'float' | 'integer-date-only' | 'string-date-only' | 'string-time-only'
$date_type['1.3.6.1.2.1.25.1.2'] = 'dateandtime';     // 'hrSystemDate' contains DateAndTime octet-string(8)
$date_type['1.3.6.1.4.1.119.2.3.239.1.1'] = 'string'; // 'nfaEventOccurTime' contains sting type of date

?>

