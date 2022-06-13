Adapter Installer
=================


Overview
--------

By default, the adapters are installed in the MSActivator container msa_dev under `/opt/devops/Openmsa_Adapters` which is a git repository linked to this github repository

using per-adapter properties files found in `/opt/devops/Openmsa_Adapters/adapters/<adapter dir>/conf/`:

- device.properties
This file defines the properties that will be used an adapter avalable on the UI

- sms_router.conf
This file defines the properties that are used by the CoreEngine to define the adapter implementation that will be used.

These 2 properties files must be placed in the adapter's directory into sub-directory `conf/`.

Whenever a change is done to one of the files `sms_router.conf` or `device.properties` msa_api and msa_sms containers have to be restarted

	sudo docker-compose restart msa-api
	sudo docker-compose restart msa-sms
	sudo docker-compose restart msa-monitoring
	sudo docker-compose restart msa-alarm
	sudo docker-compose restart msa-bud
	
Changes on the adapter code (PHP scripts) do not require any restart.	

Defining adapter properties
---------------------------

The installer script expects to find the following files:

	device.properties
	sms_router.conf

under sub-directory `conf/` in the adapter's directory.

Refer to the adapter wiki page (see above) for details on the meaning
and acceptable values for each property.

Check existing adapter properties files e.g. the `f5_bigip` or
the `vmware_vsphere` adapter `conf/` sub-directories.  These are
example sets of properties files:

- [`vmware_vsphere`](../adapters/vmware_vsphere/conf)
- [`f5_bigip`](../adapters/f5_bigip/conf)
- [`linux`](../adapters/linux_generic/conf)

Enable/Disable an adapter
-------------------------

By default all the adapters under `/opt/devops/Openmsa_Adapters` in the MSActivator container msa_dev are available to the CoreEngine but it is possible to enable/disable an adapter in the UI by editing the file device.properties and setting the flag `obsolete` to true or false. 

To update the UI, you need to restart the container msa_api

	sudo docker-compose restart msa_api

Un-installing
-------------

To deactivate an adapter, edit the file device.properties and set the flag obsolete to true

To completely un-intall an adapter, simply remove the addapter files from `/opt/devops/Openmsa_Adapters/adapters`


