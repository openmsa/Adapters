# MSA-Device-Adaptors


The MSActivator(TM) is a multi-tenant, full lifecycle management framework developed for agile service
design and assurance, making automation not only possible - but easy.

### Device Adaptors

The MSActivator comes with several pre-built device adaptors providing all the necessary functionality lifecycle management from provisioning to image and asset management. Adaptors provide the necessary interface to communicate with different devices. 

### Installation

To install a provided (existing) device adaptor, place the relevant code in the below directory on your MSA:
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
| AWS | Generic | aws (dependency: vendor) |


```NOTE: The section below is deprecated as the adapter installer script can be used to add a new DA```


### Create a new Device Adaptor
##### Create new Model/Manufacturer
###### Manufacturer

To add your new vendor, edit
```sh
/opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties
```
There are 3 fields:
 - id: must be unique and superior to 10,000
- name: you new manufacturer name
- supported: define if MSA support the device. 1 means yes.

| <ManufacturerID> | <ManufacturerName> | <isSupported> |
| ------ | ------ | ------ |
| 1 | CISCO | $UBI_VSOC_SUPPORT_CISCO_DEVICE |
| 14 | GENERIC | $UBI_VSOC_SUPPORT_GENERIC_DEVICE |
| 17 | FORTINET | $UBI_VSOC_SUPPORT_FORTINET_DEVICE |
| 18 | JUNIPER | $UBI_VSOC_SUPPORT_JUNIPER_DEVICE |


Example:
```sh
10001,"NEWMAN",1
```

###### Model
The custom folder can be created under /opt/ubi-jentreprise/resources/templates/conf/device/custom, with models.properties, modelFamilies.properties or manufacturers.properties....with ncuser rights

It allows to override the default files delivered by jentreprise RPM, subsequent upgrades will not modifiy this custom files.

To add a new model, edit 
```sh
/opt/ubi-jentreprise/resources/templates/conf/device/custom/models.properties
```
There are 16 fields:
- model id: must be unique and superior to 10000
- manufacturer id: from previous file (manufacturers.properties)
- type: S->software; H->Hardware
- obsolete: use 0 per default (Supported)
- starcenterEnabled
- familyId: use 0 per default (Generic Family)
- managed: use 1 per default (Model is managed)
- utm: 0 or 1 (detailed report)
- proxy: 0 or 1 (detailed report)
- wizard: use 1 per default (device creation using wizard in GUI)
- oec: use 0 per default
- category: use U per default (Unknow)
- detailedReportMail: 0 or 1 (detailed report)
- detailedReportFirewall: 0 or 1 (detailed report)
- detailedReportVpn: 0 or 1 (detailed report)

|<ModelID> | <ManufacturerID> | <ModeleName> | <type> | <obsolete> | <starcenterEnabled> | <familyId> | <managed> | <utm> | <proxy> | <wizard> | <oec> | <category> | <detailedReportMail> | <detailedReportFirewall> | <detailedReportVpn>|
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
|103 | 1 | "SW300" | "H" | 0 | 0 | 0 | 1 | 0 | 0 | 1 | 1 | SW | 0 | 0 | 0|
|104 | 1 | "CATALYST IOS" | "H" | 0 | 0 | 0 | 1 | 0 | 0 | 1 | 0 | U | 0 | 0 | 0|
|105 | 1 | "UC540 FXO" | "H" | 0 | 1 | 0 | 1 | 1 | 0 | 0 | 1 | VR | 0 | 0 | 0|

Example:
 ```sh
 10010,10001,"NewMod","H",0,1,0,1,0,0,1,0,U,0,0,0
 ```
###### Define the model identifier
To define the model details please run the following:
```sh
$cp /opt/ses/templates/server_ALL/sdExtendedInfo.properties /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties 
```
Edit as follows:

```sh
## Manufacturer name
sdExtendedInfo.router.<ManufacturerID>-<ModelID> = <modelIdentifier>

## Manufacturer name
sdExtendedInfo.jspType.<ManufacturerID>-<ModelID> = <modelIdentifier>
```
Example:
```sh
## NewMan
sdExtendedInfo.router.10001-10010 = NewMod

## NewMan
sdExtendedInfo.jspType.10001-10010 = NewMod
```

###### Define the model features
Copy the following and add your < modelIdentifier > for each allowed feature:
```sh
$cp /opt/ses/templates/server_ALL/manageLinks.properties /opt/ses/properties/specifics/server_ALL/manageLinks.properties
```
```sh
siteLink.initialProv.models= <modelIdentifier> ciscoCatalystIOS pix63Pix63 psgv100wPsgv100w vmwareHost vmwareVM ciscoSA500
device.wizard.automatical.update.models = <modelIdentifier> ciscoUC500 ciscoUC320 ciscoSW300
```
Example:
```sh
siteLink.initialProv.models= NewMod ciscoCatalystIOS pix63Pix63 psgv100wPsgv100w vmwareHost vmwareVM ciscoSA500
device.wizard.automatical.update.models = NewMod ciscoUC500 ciscoUC320 ciscoSW300
```

##### Enable new manufacturer support on GUI (Manufacturer only)
Copy the following and add your < newMan > to support your new manufacturer:

```sh
$cp /opt/ses/templates/server_ALL/ses.properties /opt/ses/properties/specifics/server_ALL/ses.properties
```
```sh
soc.device.supported.<newMan_toLowerCase>=1
```
Example:
```sh
soc.device.supported.newman=1
```
NOTE: Manufacturer name must be in lowercase
###### Enable new manufacturer support on repository
Copy and add:
```sh
$cp /opt/ses/templates/server_ALL/repository.properties /opt/ses/properties/specifics/server_ALL/repository.properties 
```
A new repository manufacturer:
```sh
repository.manufacturer= NETASQ CISCO JUNIPER FORTINET VMWARE ONEACCESS <newMan_toUpperCase> BLUECOAT
```
Example:
```sh
repository.manufacturer= NETASQ CISCO JUNIPER FORTINET VMWARE ONEACCESS NEWMAN BLUECOAT
```
NOTE: Manufacturer name must be in upper case
Your new model information:
```sh
repository.model.<newMan>=<ManufacturerID>-<ModelID>
```
Example:
```sh
repository.model.newMan=10001-10010
```
Your repository access:
```sh
repository.access.<newMan_toLowerCase>=|<feature1>|<feature2>|<...>
```
Example:
```sh
repository.access.newMan=|Configuration|Firmware|CommandDefinition|Datafiles|Reports|License|
```
NOTE : Manufacturer name must be in lower case.
##### Device Adaptor
###### Create a new device adaptor
The PHP files used to manage the device will be located into the folder:
```sh
/opt/sms/bin/php/<model>/
```
the asset script called <model>_mgmt.php and the poll script called <model>_poll.php are located into the folder:
```sh
/opt/sms/bin/php/polld/
```
###### Link a model to a device adaptor
This feature is used to manage the configuration of the router by updating it accordingly to the database information.
The managed devices are selected by two parameters:
- Manufacturer ID (MANID)
- Model ID (MODID)

Using the MANID and MODID, it is possible to describe in the SEC Engine where the PHP files are located.
New managed devices have to be declared in the file:
```sh
/opt/sms/devices/<model>/conf/sms_router.conf
```
Example:
```sh
# MANUFACTURER_MODEL
model                                   <MANID>:<MODID>
path                                    <model>
admin-protocol                          none
asset-script-name                       <model>_mgmt.php
poll-script-name                        <model>_poll.php
```
After that, run:
```sh
service ubi-sms restart
service jboss restart
service tomcat restart
```
to restart the SEC Engine with the new configuration.
###### Lifecycle Management
The following PHP scripts have to be created in the /opt/sms/bin/php/<model>/ directory. If a script is not present, the corresponding operation on the SOC will give the "Function not supported by the device" error.
do_backup_conf.php : Generate a backup of the device configuration.
do_restore_conf.php : Restore a configuration backup on the device.
###### Image and change management
The following PHP scripts have to be created in the /opt/sms/bin/php/<model>/ directory. If a script is not present, the corresponding operation on the SOC will give the "Function not supported by the device" error.
do_update_firmware.php
do_get_running_conf.php
###### Asset management
This script should be in 
```sh
/opt/sms/bin/php/polld/<device-model>_mgmt.php
```
###### Silver Monitoring
This service is based on SNMP. It is generic add does not need any adaptation.
###### Gold Monitoring
You can also classify your syslogs thanks to following file :
```sh
[root@MSA]# cat /opt/sms/conf/sms_syslogd.conf
```

License
----

GNU GPL v3.0
