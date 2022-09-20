# ETSI-MANO VNFM Managed Entity Configuration Variables

| NAME | VALUE | DESCRIPTION | REQUIRED |
| ------ | ------ | ------ | ------ |
| BASE_URL | /ubi-etsi-mano/ | |Yes |
| HTTP_PORT | 8089 | |Yes |
| AUTH_MODE | basic | Two possible values: 'basic' or 'oauth_v2'. If 'oauth_v2' set as value, 'SIGNIN_REQ_PAH' and 'TOKEN_XPATH' configuration variables must be added as well (as in the next two rows). | Yes |
| SIGNIN_REQ_PATH | http://192.168.1.23:8110/auth/realms/mano-realm/protocol/openid-connect/token  | Keycloak server URL allows to get the NFVO authentication. | No (basic), Yes (oauth_v2)|
| TOKEN_XPATH | /root/access_token | | No (basic), Yes (oauth_v2)|
