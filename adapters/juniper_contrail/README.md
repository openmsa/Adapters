Juniper Contrail adaptor
====================

A Juniper Contrail acts as Neutron component of an Openstack. 

There is no specific part in the implementation of the DA except the authentication that is different because it is not done on the Juniper Contrail but through the Keystone component of the same Openstack.

For more details about Openstack components and their roles : https://en.wikipedia.org/wiki/OpenStack

The authentication is described in this document :  https://docs.openstack.org/api-quick-start/api-quick-start.html

So a Juniper contrail managed entity should have several configuration variables for the authentication:

## KEYSTONE_URL
The URL for requesting an authentication token to the keystone component of Openstack.

## KEYSTONE_PORT
The TCP port to reach the keystone component of Openstack.

## KEYSTONE_PROTOCOL
The protocol to access the keystone component of Openstack.

## KEYSTONE_USER_DOMAIN_NAME
The user domain for authentication.

## KEYSTONE_USER_NAME
The user name for authentication.

## KEYSTONE_USER_PASSWORD
The password for authentication.

## KEYSTONE_PROJECT_DOMAIN_NAME
The project domain for authentication. (Optional)

## KEYSTONE_PROJECT_NAME
The project name for authentication. (Optional)


If the authentication is not required, no variable to create or at least KEYSTONE_URL should not be created.

REST API of Contrail are described here https://www.juniper.net/documentation/en_US/contrail20/information-products/pathway-pages/api-guide-2011/index.html

The Juniper Contrail DA is able to manage REST XML or REST JSON Microservices.
For the support of REST JSON MS the variable **REST_JSON** has to be created with a value of **1**.
