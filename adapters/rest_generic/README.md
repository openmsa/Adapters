Generic REST adaptor
====================

# Available configuration variables

## PROTOCOL
Use this configuration to select the protocol for the REST API requets
* values: http, https
* default: https 

## AUTH_MODE
Use this configuration variable to select the authentication scheme: no authentication, BASIC authentication or token based authentication
* values: no-auth, BASIC, token
* default: BASIC 

## AUTH_HEADER
Use this configuration variable to set the HTTP header to use for setting the authentication token.
This configuration variable will only be used if AUTH_MODE has been set to **token**.
* values: 'Authorization: Bearer',  'X-chkp-sid',...

Important: many REST API are using custom, specific authorization header, use this configuration to set the one required by the REST API.

## SIGNIN_REQ_PATH
Use this to set the API sign in request path. This is specific to the API.
It's a mandatory configuration when AUTH_MODE is set to token, it will be ignored for the other modes
* values: API specific, check the API documentation

## TOKEN_XPATH
The XPath to get the token from the authentication response.
The Generic REST adapter will handle JSON response by transforming the JSON string into an XML string to allows the use of XPath. The root element of the generated XML will be set to <root>.
The transformation to XML will be trigered if the Content-Type HTTP header is set to application/json
* default: //root/token

## HTTP_HEADER
Use this to list the HTTP header to pass to the API HTTP requests.
This configuration should be specified as a | separated list of "key: value"
###Example:
HTTP_HEADER = Content-Type: application/json | Accept: application/json
default: Content-Type: application/json | Accept: application/json
 

# Sample configurations
## BASIC authentification
The Generic REST adapter is designed to work by default with 
* BASIC REST authentication
* HTTPS protocol
* JSON content type and accept HTTP headers

## Token based authentication
For supporting a token based authentication REST API, the configuration variables below should be set:
* AUTH_MODE : token
* SIGNIN_REQ_PATH : /depend/on/your/API
* This configuration will use the HTTP authentication header 'Authorization: Bearer'

## No authentication
For supporting API that don't require any authentication, you should set 
AUTH_MODE : ignore-auth

Important: by default the device credential are mandatory in the device creation/update form.
You need to input some dummy credentials that will be ignored by the adapter.

## Maximum time allowed for HTTP connection and transfer
For customising the Maximum time allowed for the HTTP connection and transfer
The default value is set to 50 seconds.
CONN_TIMEOUT : <an integer>

