Generic adaptor for Linux
=======================

The Generic Adapter for Linux relies on SSH for running remote CLI commands on any Linux setup.

# Configuration modes

## login/password (default)
This is the default mode of the adapter. Set the login and password at the managed entity level.

The adapter will use the credentials for activation, configuration and asset management.

## SSH Key

The adapter will use a SSH key for every connection to the managed entity. The ssh CLI command will use the option "-i" to specify the SSH key to use.

### Install the SSH key in the MSActivator
To enable this mode you'll first have to install the SSH key in the MSactivator.

#### copy the key on the container *msa_sms*: 

From the directory where the docker-compose file is installed, run:
> docker-compose exec msa_sms mkdir -p /home/ncuser/.ssh/
> MSA_SMS=`docker ps -aqf "name=msa_sms"` 
> docker cp <SSH KEY FILE> $MSA_SMS:/home/ncuser/.ssh/



