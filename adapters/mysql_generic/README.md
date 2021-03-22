# Abstract

The adapter is proposed to work with MySQL RDBMS via [mysql command-line tool](https://linux.die.net/man/1/mysql). The device adapter expects to have mysql tool installed inside sms container. The device adapter code is used simple username/password authentication and batch output - coloumns are sparated by tab. The device adapter presents mysql tool output to microservice skipping first line. Microservice command is actually mapped to 'execute' instruction of mysql tool. 

# Prerequisites
The [mysql command-line tool](https://linux.die.net/man/1/mysql) should be installed in sms container.
* Install mysql tool from repo for PoC/test environments:
```bash
  docker-compose exec msa_sms yum install -y mysql
```
* For production environment, please contact with UBiqube

# Usage
The device adapter uses managed entity IP address, username and password to connect to remote database. Managed entity variable 'DATABASE' should be configured before start to use. The variable should have a value equaled to database name. Microservice command is mapped to 'execute' instruction of mysql tool, that is why any command could be used.
