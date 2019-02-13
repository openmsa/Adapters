Configuration plug for a new device adaptor
===========================================


files:
- device.properties
- features.properties
- repository.properties


plug info into:
- sdExtendedInfo.properties
- manufacturers.properties
- models.properties
- manageLinks.properties
- repository.properties
- ses.properties


Info plugs:
-----------

from file device.properties:

model.name = vSphere
manufacturer.name = VMware

=>

generate info for conf files entries:

using:

keyname = <manufacturer.name><model.name>
deviceid = <manufacturer.id>-<model.id>


in file sdExtendedInfo.properties:
----------------------------------

sdExtendedInfo.modelKey.<keyname> = <keyname>
sdExtendedInfo.router.<deviceid> = <keyname>
sdExtendedInfo.jspType.<deviceid> = <keyname>

in file manufacturers.properties:
---------------------------------

<manufacturer.id>,"<manufacturer.name>",1


in file models.properties:
--------------------------

To add a new model, edit /opt/ubi-jentreprise/resources/templates/conf/device/models.properties

There are 16 fields:

    model id: must be unique and superior to 10000
    manufacturer id: from previous file (manufacturers.properties)
    type: S->software; H->Hardware
    obsolete: use 0 per default (Supported)
    starcenterEnabled
    familyId: use 0 per default (Generic Family)
    managed: use 1 per default (Model is managed)
    utm: 0 or 1 (detailed report)
    proxy: 0 or 1 (detailed report)
    wizard: use 1 per default (device creation using wizard in GUI)
    oec: use 0 per default
    category: use U per default (Unknow)
    detailedReportMail: 0 or 1 (detailed report)
    detailedReportFirewall: 0 or 1 (detailed report)
    detailedReportVpn: 0 or 1 (detailed report) 

101,1,"IPS4200","H",1,0,1,1,1,0,1,0,SR,0,0,0
102,1,"AP541","H",1,0,1,1,1,0,1,0,SR,0,0,0
103,1,"SW300","H",0,0,0,1,0,0,1,1,SW,0,0,0
104,1,"CATALYST IOS","H",0,0,0,1,0,0,1,0,U,0,0,0
105,1,"UC540 FXO","H",0,1,0,1,1,0,0,1,VR,0,0,0
<ModelID>,<ManufacturerID>,<ModeleName>,<type>,<obsolete>,<starcenterEnabled>,<familyId>,<managed>,<utm>,<proxy>,<wizard>,<oec>,<category>,<detailedReportMail>,<detailedReportFirewall>,<detailedReportVpn>

Example:

10010,10001,"NewMod","H",0,1,0,1,0,0,1,0,U,0,0,0


in file manageLinks.properties:
-------------------------------

from features.properties:

insert content from DA conf file


in file repository.properties:
------------------------------

insert 3 entries:

repository.manufacturer = *(insert in list)* <manufacturer.name (uppercase)>

repository.model.<manufacturer.name (lowercase)> = <deviceid>


repository.access.<manufacturer.name (lowercase)> = single line values
                                           read from file, | separated



in file ses.properties:
-----------------------

soc.device.supported.<manufacturer.name (toLowerCase)>=1


