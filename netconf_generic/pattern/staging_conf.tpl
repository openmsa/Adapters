# Staging Configuration to Enable Netconf over SSH
-------------------------


edit system
set root-authentification plain-test-password
{$SD->SD_PASSWD_ENTRY}
{$SD->SD_PASSWD_ENTRY}
		
edit
set interface ge-0/0/0 unit 0 family inet address {$SD->SD_IP_CONFIG}
set system services netconf ssh
set	system services ssh
set security zones security-zone trust interfaces ge-0/0/0 host-inbound-traffic system-service ping
set security zones security-zone trust host-inbound-traffic system-services ssh
set security zones security-zone trust host-inbound-traffic system-services netconf


commit
quit
