OpenMSA-1.2
===========

Release date:

Features
--------

New Device Adaptors

- `hp2530` for HPE Switch OS Aruba Type
- `hp5900` for HPE Switch OS H3C Type
- `nec_pflow_pfcscapi` for controlling ProgrammableFlow Controller using REST API
- `nec_nfa` for Network Flow Analyzer (Log Management Function only)
- `nec_ix` for NEC IX devices (NEC IX2000/IX3000 series)
- `fortinet_json` for Fortinet devices (FortiManager, FortiAnalyzer)


Improvements
------------

Device Adaptor Customizations

- `catalyst_ios`
  - improvement on telnet connection function
  - added handling for new error messages


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
