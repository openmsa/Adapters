# MSA-Device-Adaptors

MSActivator is a multi-tenant, full lifecycle management framework developed for agile service
design and assurance, making automation not only possible - but easy.


### Device Adaptors

MSA comes with several prebuilt device adaptors providing all the necessary functionality lifecycle management from provisioning to image and asset management. Adaptors provide the necessary interface to comminicate with different devices. 

### Installation

To install a provided (existing) device adaptor place the relevant folder and code under on your MSA server:
```sh
$ /opt/sms/bin/php/
```


#### Device adaptors

The following device adaptors are provided as standard:

|Vendor| Device | Related Folder |
| ------ | ------ | ------ |
| Cisco | ISR IOS | cisco_isr |
| Cisco | CSR/ASR | cisco_isr |
| Cisco | ASA | cisco_asa_generic |
| Cisco | Catalyst IOS | catalyst_ios |
| Cisco | WSA, vWSAS | wsa |
| Cisco | ESA, vESA | esa |
| Juniper | Junos | juniper_srx |
| Fortinet | Fortigate | fortinet_generic |
| Fortinet | Fortiweb | fortinet_generic |
| Oneaccess | OneOS | oneaccess_lbb |
| Stormshield | SN Series | netasq |
| Palo Alto | Chassis | paloalto_generic |
| Palo Alto | Vsys | paloalto_generic |
| Palo Alto | VA | paloalto_generic |
| Linux | Generic | linux_generic |
| Openstack | Generic | openstack_keystone_v3 |

License
----

Freeware

