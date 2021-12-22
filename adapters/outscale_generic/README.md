OUTSCALE Generic REST adaptor
====================

OUTSCALE Generic adapter is based on the REST Generic adapter for using [OUTSCALE REST](https://docs.outscale.com/en/userguide/API-Documentation.html) API to design Microservices.

In this document you will find the managed entity configuration variables to set.

# Available configuration variables

For non production environment with BASIC authentication

* AUTH_FQDN: api.[OUTSCALE_REGION].outscale.com 	
* AUTH_MODE: BASIC 	
* AWS_SIGV4: osc 	
* PROTOCOL: https 

NOTE: replace [OUTSCALE_REGION] by the your region (ex: eu-west-2) to configure the FQDN of the API server of your region.