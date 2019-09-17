snmp-server community {$SD->SD_SNMP_COMMUNITY} RO 39
snmp-server host {$SD->SD_NODE_IP_ADDR} version 2c {$SD->SD_HOSTNAME}
snmp-server enable traps snmp linkup
!