Adapter Installer
=================


Overview
--------

MSActivator comes with an adapter installer script: `../bin/da_installer`.

The script updates the following MSA global configuration files:

- manufacturers.properties
- models.properties

using per-adapter properties files found in `<adapter dir>/conf/`:

- device.properties

The insertion of adapter info into the global configuration files
is described in `./Configuration_plug_for_adapters.md`.

The per-adapter properties files must be placed in the adapter's directory
into sub-directory `conf/` along with the Core Engine adapter configuration file: `sms_router.conf`.

The installer script performs basic compatibility checks on values provided
for `model-id` and `manufacturer-id`, as these are currently double-defined
in files `sms_router.conf` and `device.properties`.

Usage
-----

The installer script is run as follows:

	da_installer -i <adapter dir>

This updates the MSA global configurations files adding the adapter's info.
Restart of most MSA services is then required, as per product documentation:

	sudo docker-compose restart msa_api
	sudo docker-compose restart msa_sms

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

Enable/Disable an adapter
-------------------------

By default all the adapters under `/opt/devops/Openmsa_Adapters` in the MSActivator container msa_dev are available to the CoreEngine but it is possible to enable/disable an adapter in the UI by editing the file device.properties and setting the flag obsolete to true or false. 

To update the UI, you need to restart the container msa_api

	sudo docker-compose restart msa_api

Un-installing
-------------

There is currently no support for un-installing an adapter's definition.



