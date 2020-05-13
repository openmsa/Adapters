How to Install a New Adapter
============================

Once the per-adaptor properties files are ready in the directory <adapter_dir>/conf as per the document on the [Adaptor Installer](Adaptor_installer.md) as well at the Core Engine configuration file sms_router.conf you can install the adaptor on an MSActivator instance.

The tutorial below will use the REST Generic adapter as an example but the steps would be similar for any other adapter.

Whenever there is a reference to git, the tutorial will use the repository openmsa/Adaptors.git but if you are planning to contribute to the code or the doc, you may prefer to fork the Adaptors repository in your git and clone it.  

Step 1: clone the Adaptors git repository on your MSActivator
------ 
```
# cd /opt/
# git clone https://github.com/openmsa/Adaptors.git
```

Step 2: use a symlink to install the source code of the adaptor
------
```
# cd /opt/sms/bin/php
# ln -s /opt/Adaptors/adapters/rest_generic rest_generic
# chown -R ncuser.ncuser rest_generic
```

Step 3: install the Core Engine configuration file
------
```
# cd /opt/sms/templates/devices/
# ln -s /opt/Adaptors/adapters/rest_generic rest_generic
# chown -R ncuser.ncuser rest_generic
# service ubi-sms restart
```
if you are running a MSA on Centos 7.5+ use
```
# systemctl restart ubi-sms
```
check the status of the Core Engine:
```
# service ubi-sms status
```

Step 4: enable the adaptor on the frontend
------

use the da_installer script:
```
# /opt/Adaptors/bin/da_installer install /opt/Adaptors/adapters/rest_generic/
```
restart the MSA portal
```
# service restart jboss
# service restart tomcat 
```
alternatively on MSA-2.0 (Centos 7.5)
```
# systemctl restart wildfly
# systemctl restart tomcat
```
