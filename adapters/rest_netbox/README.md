REST adaptor for Netbox
=======================

API doc: https://netbox.readthedocs.io/en/stable/

The REST adaptor for Netbox is based on the Generic REST adator and will allow you to use REST / Netbox vendor and model on the UI. 

To activate the adapter, you need to configure these configuration variables on you managed entity:

*HTTP_HEADER* : Authorization:Token <TOKEN>|Content-Type: application/json
*PROTOCOL* : HTTP

The TOKEN can be generated from the Netbox web based management UI: https://netbox.readthedocs.io/en/stable/rest-api/authentication/

For more info on the Generic REST Adapter: https://github.com/openmsa/Adapters/blob/master/adapters/rest_generic/README.md