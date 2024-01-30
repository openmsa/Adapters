REST adaptor for Netbox
=======================

API doc: https://netbox.readthedocs.io/en/stable/

The REST adaptor for GENIE Atm device based on the Generic REST adator and will allow you to use REST / GENIE vendor and model on the UI. 

To activate the adapter, you need to configure these configuration variables on you managed entity:

*APIDATA* : -d 'request=[{"display_data":"yes"}]'
*AUTH_MODE* : BASIC
*HTTP_HEADER* : Content-Type: application/json | Accept: application/json
*PROTOCOL* : https

For more info on the Generic REST Adapter: https://github.com/openmsa/Adapters/blob/master/adapters/rest_generic/README.md
