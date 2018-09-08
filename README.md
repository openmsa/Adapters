MSA-Device-Adaptors
===================


[OpenMSA](https://openmsa.co) is the community incarnation of MSActivator(TM),
a multi-tenant, full lifecycle management framework for agile service design
and assurance.

OpenMSA comes as 3 separate components:
- OpenMSA .ova              (downloadable)
- Device Adaptors           (repository)
- Workflows & Microservices (repository)


This repository contains Device Adaptors.


Device Adaptors
---------------

MSActivator comes with several pre-built device adaptors,
providing all the necessary functionality for lifecycle management,
from provisioning to image and asset management.

Adaptors provide the necessary interface to communicate with different devices.

Details on how to get started with device adaptors is available
in the [wiki](https://github.com/openmsa/Device-Adaptors/wiki).

Installing via Composer
-----------------------

If you like to use [Composer](https://getcomposer.org/) in your PHP projects, you can also use this to install device adaptors into your OpenMSA installation.  Here is a sample _composer.json_ file to run in your environment:

	{
	    "repositories": [
	        {
	            "type": "vcs",
	            "url": "https://github.com/openmsa/Device-Adaptors"
	        }
	    ],
	    "config": {
	        "vendor-dir": "/opt/sms/bin/php/"
	    },
	    "require": {
	        "openmsa/adaptors": "v1.1.1"
	    }
	}

Now run the following to install version 1.1.1 of the adaptors into your local OpenMSA installation (note they will be installed into the _/opt/sms/bin/php/openmsa/adaptors/_ directory):

	$ composer install
	Loading composer repositories with package information
	Installing dependencies (including require-dev) from lock file
	Package operations: 1 install, 0 updates, 0 removals
	  - Installing openmsa/adaptors (v1.1.1): Downloading (100%)
	Generating autoload files


Contributing to OpenMSA
-----------------------

- `CONTRIBUTING.md`
- `doc/Manufacturer_and_Model_ID_Convention.md`


Licenses
--------

- `LICENSE.md`
- third-party components may have different licenses, see e.g. `vendor/`.
