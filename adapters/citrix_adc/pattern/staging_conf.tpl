# Staging Configuration
-------------------------
		
edit
set interface ge-0/0/0 unit 0 family inet address {$SD->SD_IP_CONFIG}
set security zones security-zone trust interfaces ge-0/0/0 host-inbound-traffic system-service ping

edit system
set root-authentification plain-test-password
{$SD->SD_PASSWD_ENTRY}
{$SD->SD_PASSWD_ENTRY}

commit
quit
