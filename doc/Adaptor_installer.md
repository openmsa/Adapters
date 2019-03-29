Adaptor installer
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

The process of creating a new adoptor, including php code requirements,
samples and adaptor info specification, is described in the
[Adaptors wiki page](https://github.com/openmsa/Device-Adaptors/wiki).

The per-adaptor properties files must be placed in the adaptor's directory
into sub-directory `conf/` along with the adaptor's `sms_router.conf` file.

The installer script performs basic compatibility checks on values provided
for `model-id` and `manufacturer-id`, as these are currently double-defined
in files `sms_router.conf` and `device.properties`.


Usage
-----

The installer script is run as follows:

	da_installer -i <adaptor dir>


This updates the MSA-global configurations files adding the adaptor's info.
Restart of most MSA services is then required, as per product documentation:

	service restart jboss
	service restart ses
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
and acceptable values for each property.  Check the vsphere adaptor
for an example set of properties files: `../vmware_vsphere/conf`.


Un-installing
-------------

There is currently no support for un-installing an adaptor's definition.



