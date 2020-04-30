Adaptor Installer
=================


Overview
--------

OpenMSA comes with an adaptor installer script: `../bin/da_installer`.

The script updates the following MSA-global configuration files:

- sdExtendedInfo.properties
- manufacturers.properties
- models.properties
- manageLinks.properties
- repository.properties
- ses.properties

using per-adaptor properties files found in `<adaptor dir>/conf/`:

- device.properties
- features.properties
- repository.properties


The insertion of adaptor info into the global configuration files
is described in `./Configuration_plug_for_adaptors.md`.

The per-adaptor properties files must be placed in the adaptor's directory
into sub-directory `conf/` along with the Core Engine adaptor configuration file: `sms_router.conf`.

The installer script performs basic compatibility checks on values provided
for `model-id` and `manufacturer-id`, as these are currently double-defined
in files `sms_router.conf` and `device.properties`.

:heavy_exclamation_mark:
The installer does not install the adaptor code (in PHP) in the MSActivator core engine and it does not install the configuration file sms_router.conf. 
For installation best practice please have a look at [how to install a new adapter](How_to_install_a_new_adapter.md)


Usage
-----

The installer script is run as follows:

	da_installer -i <adaptor dir>


This updates the MSA-global configurations files adding the adaptor's info.
Restart of most MSA services is then required, as per product documentation:

	service restart jboss
	service restart tomcat
	service restart sms


Defining adaptor properties
---------------------------

The installer script expects to find the following files:

	device.properties
	features.properties
	repository.properties
	sms_router.conf

under sub-directory `conf/` in the adaptor's directory.

Refer to the Adaptors wiki page (see above) for details on the meaning
and acceptable values for each property.

Check existing adaptor properties files e.g. the `f5_bigip` or
the `vmware_vsphere` adaptors `conf/` sub-directories.  These are
example sets of properties files:

- [`vmware_vsphere`](../adapters/vmware_vsphere/conf)
- [`f5_bigip`](../adapters/f5_bigip/conf)


Un-installing
-------------

There is currently no support for un-installing an adaptor's definition.



