MODIFY ON FORCE
SYSTEM IDENT "{$SD->SD_HOSTNAME}"
CONFIG CONSOLE SETPASSPHRASE {$SD->SD_PASSWD_ENTRY}
CONFIG OBJECT HOST NEW name=defaultgw ip={$SD->SD_INTERFACE_list.E->INT_IP_GW} type=router resolve=static comment="Default gateway" update=1
CONFIG OBJECT HOST NEW name=host_NCM ip={$ncm_ip_addr} type=host resolve=static comment="NCM Server" update=1
CONFIG OBJECT HOST NEW name=host_MGMT ip={$SD->SD_IP_CONFIG} type=host resolve=static comment="Management interface" update=1
CONFIG OBJECT ACTIVATE
CONFIG NETWORK INTERFACE ADDRESS ADD ifname={$SD->SD_INTERFACE_list.E->INT_NAME} address={$SD->SD_IP_CONFIG} mask={$SD->SD_INTERFACE_list.E->INT_IP_MASK} AddressComment="WAN interface"
CONFIG NETWORK INTERFACE UPDATE ifname={$SD->SD_INTERFACE_list.E->INT_NAME} protected=0
CONFIG NETWORK DEFAULTROUTE SET name=defaultgw type=ipv4
CONFIG NETWORK ACTIVATE
{if $SD->SD_LOG_MORE}
CONFIG COMMUNICATION SYSLOG state=1 server=host_NCM
CONFIG COMMUNICATION ACTIVATE
CONFIG LOG ALARM syslog=1
CONFIG LOG ACTIVATE
{/if}
CONFIG SLOT UPLOAD type=filter slot=02 name="Allow NCM" < rules.txt
CONFIG SLOT ACTIVATE type=filter slot=02
