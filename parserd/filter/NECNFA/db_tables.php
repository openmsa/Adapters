<?php
/*
 * Version: $Id$
 * Created: 
 */

// format: array(syslog field name, DB or ES field name, DB type, DB len)

$database_def = array(
    'ElasticSearch' => array(
        'Insert_into_fw_rawdata' => $es_rawdata,

        'Insert_into_ips_rawdata' => $es_rawdata,

        'Insert_into_ctf_rawdata_for_Web_Filter' => $es_rawdata,

        'Insert_into_ctf_rawdata_for_Mail_Spam' => $es_rawdata,

        'Insert_into_ctf_rawdata_web_category' => $es_rawdata,

        'Insert_into_ctf_rawdata_for_Web_Virus' => $es_rawdata,

        'Insert_into_logs' => $es_rawdata,
    ),

);

?>

