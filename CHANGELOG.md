openmsa-1.3
===========

Release date:


Improvements

- source tree layout
- rpm packaging support


openmsa-1.2
===========

Release date: 2019-07-11


New adapters

- `hp2530`: HPE Switch OS Aruba Type
- `hp5900`: HPE Switch OS H3C Type
- `nec_pflow_pfcscapi`: NEC ProgrammableFlow Controller, using REST API
- `nec_nfa`: NEC Network Flow Analyzer (Log Management Function only)
- `nec_ix`: NEC IX devices (NEC IX2000/IX3000 series)
- `fortinet_jsonapi`: Fortinet devices (FortiManager, FortiAnalyzer)
- `veex_rtu`: VeEX RTU devices


Preview Adapter Installer

- `bin/da_installer`
- `doc/Adaptor_installer.md`


PHP-7.2

- the core engine uses a newer version of PHP
- adapters code no longer compatible with pre-17.1 MSA


Improvements

- `catalyst_ios`: better telnet connection, handle new error messages
- `14 adapters`: code synced to last version of 17.1 MSA


openmsa-1.1
===========

Release date: 2018-07-25


New adapters

- `aws_generic`: Amazon VMs
- `cisco_nexus9000`: Cisco Nexus 9000 devices
- `vmware_vsphere`: VMware vSphere VMs
- `netconf_generic`: OpenDaylight Netconf devices

Improvements

- `cisco_isr`, `catalyst_ios`: add error strings, fix Catalyst initial provisioning
- `fortinet_generic`: license status check (VDOM), handle new firmwares
- `openstack_keystone_v3`: multiple enhancements and bug fixes
- `linux_generic`: multiple enhancements and bug fixes
- `paloalto_generic`: add support for PaloAlto PAN-OS 8.1


openmsa-1.0
===========

Release date: 2017-12-14

Initial github release
