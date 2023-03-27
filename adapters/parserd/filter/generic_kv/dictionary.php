<?php


$dictionary = array (
	'action_group' => array (
		'pass' => 'Accepted',
		'block' => 'Blocked',
		'accept' => 'Accepted',
		'deny' => 'Blocked',
		'detected' => 'Accepted',
		'dropped' => 'Blocked',
		'reset' => 'Blocked',
		'reset_client' => 'Blocked',
		'reset_server' => 'Blocked',
		'drop_session' => 'Blocked',
		'pass_session' => 'Accepted',
		'clear_session' => 'Blocked',
		'passthrough' => 'Accepted',
		'blocked' => 'Blocked',
		'clean' => 'Accepted',
		'N/A heuristic' => 'Blocked',
		'start' => 'Accepted',
	),
	'Priority' => array (
		'0' => '0',
		'emergency' => '0',
		'alert' => '1',
		'critical' => '2',
		'error' => '3',
		'warning' => '4',
		'notice' => '5',
		'information' => '6',
		'debug' => '7',
		'1' => '1',
		'2' => '2',
		'3' => '3',
		'4' => '4',
		'5' => '5',
		'6' => '6',
		'7' => '7',
	),
	'Filter' => array (
		'virus' => 'Antivirus',
		'webfilter' => 'Web Filter',
		'emailfilter' => 'Spam Filter',
	),
	'Action' => array (
		'blocked' => 'Blocked',
		'detected' => 'Tagged',
		'passthrough' => 'Allowed',
		'allow' => 'Allowed',
	),
	'Log_type' => array (
		'00' => 'Traffic Log',
		'01' => 'Event Log',
		'06' => 'Content Archive',
		'02' => 'Antivirus Log',
		'03' => 'Web Filter Log',
		'04' => 'Attack Log',
		'05' => 'Spam Filter Log',
		'07' => 'Instant Messaging',
	  '08' => 'voip',
	  '09' => 'dlp',
	  '10' => 'app-ctrl-all',
	  '11' => 'netscan',
	  '12' => 'UTM'
	),
	'Cat_threat' => array (
		'0211' => 'Virus',
		'0212' => 'Other Filter',
		'0213' => 'Other Filter',
		'0314' => 'Other Filter',
		'0315' => 'Category Filter',
		'0316' => 'Category Filter',
		'0335' => 'Other Filter',
		'0336' => 'Other Filter',
		'0337' => 'Other Filter',
		'0508' => 'Spam',
		'0509' => 'Spam',
		'0510' => 'Spam',
		'0731' => 'Instant Messaging',
	),
	'Service_Type' => array (
		'ftp' => 'Web',
		'http' => 'Web',
		'https' => 'Web',
		'pop3' => 'Mail',
		'smtp' => 'Mail',
		'imap' => 'Mail',
	),
	'Threat' => array (
		'0211' => 'Virus',
		'0212' => 'Filename blocked',
		'0213' => 'File oversized',
		'0314' => 'content block',
		'0315' => 'URL filter',
		'0316' => 'URL blocked',
		'0317' => 'URL allowed',
		'0318' => 'FortiGuard error',
		'0335' => 'ActiveX script filter',
		'0336' => 'Cookie script filter',
		'0337' => 'Applet script filter',
		'0419' => 'Attack signature',
		'0420' => 'Attack anomaly',
		'0508' => 'Spam',
		'0509' => 'Spam',
		'0510' => 'Spam',
		'0731' => 'Instant Messaging',
		'0022' => 'Policy allowed traffic',
		'0023' => 'Policy violation traffic',
		'0038' => 'Other',
		'0100' => 'System activity event',
		'0101' => 'IPSec negotiation event',
		'0102' => 'DHCP service event',
		'0103' => 'L2TP/PPTP/PPPoE service event',
		'0104' => 'admin event',
		'0105' => 'HA activity event',
		'0106' => 'Firewall authentication event',
		'0107' => 'Pattern update event',
		'0123' => 'Alert email notifications',
		'0129' => 'FortiGate-4000 and',
		'0132' => 'FortiGate-5000 series chassis event',
		'0133' => 'SSL VPN user event',
		'0134' => 'SSL VPN administration',
		'0624' => 'HTTP Virus infected',
		'0625' => 'FTP content metadata',
		'0626' => 'SMTP content metadata',
		'0627' => 'POP3 content metadata',
		'0628' => 'IMAP content metadata',
	),
	'Accepted Services' => array (
		'1863' => 'Yes',
		'110' => 'Yes',
		'0' => 'Yes',
		'123' => 'Yes',
		'1723' => 'Yes',
		'53' => 'Yes',
		'443' => 'Yes',
		'80' => 'Yes',
	),
	'CVE_CAN' => array (
		'FGT102039558' => 'CVE-2000-0347',
		'FGT103350551' => 'CAN-2004-1120',
		'FGT101646384' => 'CAN-2004-0209',
		'FGT102039613' => 'CVE-2012-1234',
	),
	'Address_Type' => array (
		'ubiqube.net' => 'Internal',
		'ubiqube.com' => 'Internal',
		'netcelo.net' => 'Internal',
		'netcelo.com' => 'Internal',
	),
	'Country' => array (
			'Reserved' => 'Lo',
			'United States' => 'US',
	),
);

?>