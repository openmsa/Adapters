Paloalto Prisma adapter
=======================

By default this Paloalto Prisma adapter is based Generic RESTuses HTTPS protocol, BASIC authentication and application/json header for both request and responses.

It can be customized to support other protocols, authentication or headers by using managed entity [configuration variables](https://ubiqube.com/wp-content/docs/latest/user-guide/manager-guide-single.html#me_conf_var).

The list of configuration that can we used is available below

# Available configuration variables

## AUTH_FQDN (optional)
Use this configuration variable to set the authentication fqdn.  
* default: 'auth.apps.paloaltonetworks.com'

## AUTH_HEADER (optional)
Use this configuration variable to set the HTTP header to use for setting the authentication token.

* example: 'Authorization: Bearer', 'X-chkp-sid',...  
* default: 'Authorization: Bearer'

Important: many REST API are using custom, specific authorization header, use this configuration to set the one required by the REST API.

## CONN_TIMEOUT (optional)
For customizing the Maximum time allowed for the HTTP connection and transfer.  
An positive integer in seconds.  
* example: 60
* default: 50

## HTTP_HEADER (optional)
Use this to list the HTTP header to pass to the API HTTP requests.  
This configuration should be specified as a comma separated list of "key: value"  
* example: 'Content-Type: application/json', 'Accept: application/json'
* default: 'Content-Type: application/x-www-form-urlencoded'

## PROTOCOL (optional)
Use this configuration to select the protocol for the REST API requests
* default: https 

## MANAGEMENT_PORT (optional)
Use this configuration to set a specific management port. It is recommanded to not use this variable but instead to configure the Management Port during the creation of the ME.
* example: 80 or 443

## SIGNIN_REQ_PATH (optional)
Use this to set the API sign in request path.
* default: /auth/v1/oauth2/access_token

## TSG_ID (optional)
The tenant service group id to get an access token.
* default: 1844024960
