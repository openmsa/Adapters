OpenMSA-1.2
===========

Release date:

Features
--------

New Adaptors

- `hp2530` for HPE Switch OS Aruba Type
- `hp5900` for HPE Switch OS H3C Type
- `nec_pflow_pfcscapi` for controlling ProgrammableFlow Controller using REST API
- `nec_nfa` for Network Flow Analyzer (Log Management Function only)
- `nec_ix` for NEC IX devices (NEC IX2000/IX3000 series)
- `fortinet_json` for Fortinet devices (FortiManager, FortiAnalyzer)
- `Veex_RTU`


Preview Adaptor Installer

- `bin/da_installer`
- `doc/Adaptor_installer.md`


Improvements
------------

- `catalyst_ios`
  - improvement on telnet connection function
  - added handling for new error messages

- most of the code has been updated to use `PHP 7.2`
  making it no longer compatible with pre 17.1 MSA

- 13 adaptors have been updated to the last version
  available for the 17.1 MSA


OpenMSA-1.1
===========

Release date: 2018-07-25

Features
--------

- add AWS device adaptor
- add Cisco Nexus9000 device adaptor
- add VMware vSphere device adaptor

Improvements
------------

- Cisco ISR and Catalyst: add error strings, fix Catalyst initial provisioning
- Fortinet: license status check on Fortigate VDOM
- Fortinet: handle new firmwares in fortinet generic


OpenMSA-1.0
===========

Relase date: 2017-12-14

Initial github release
