Update Adapter Code
=================

This doc explains how to update the code of the adapters installed in your MSActivator instance

Overview
--------

The adapters are installed in the MSActivator container msa_dev, in the directory /opt/devops/OpenMSA_Adapters.

```
[root@21a1c0d253f2 OpenMSA_Adapters]# pwd
/opt/devops/OpenMSA_Adapters

[root@21a1c0d253f2 OpenMSA_Adapters]# ll
total 84
-rw-r--r--  1 ncuser ncuser  1524 Mar 18 15:31 CHANGELOG.md
-rw-r--r--  1 ncuser ncuser    80 Mar 18 15:31 CODE_OF_CONDUCT.md
-rw-r--r--  1 ncuser ncuser   516 Mar 18 15:31 CONTRIBUTING.md
-rw-r--r--  1 ncuser ncuser 35122 Mar 18 15:31 LICENSE.md
-rw-r--r--  1 ncuser ncuser  1338 Mar 31 12:38 README.md
-rw-r--r--  1 ncuser ncuser    28 Mar 18 15:31 VERSION.txt
drwxr-xr-x 84 ncuser ncuser  4096 Mar 31 12:38 adapters
drwxr-xr-x  2 ncuser ncuser  4096 Mar 18 15:31 bin
drwxr-xr-x  3 ncuser ncuser  4096 Mar 31 12:38 doc
drwxr-xr-x  2 ncuser ncuser  4096 Mar 18 15:31 int
drwxr-xr-x  4 ncuser ncuser  4096 Mar 18 15:31 parserd
drwxr-xr-x  2 ncuser ncuser  4096 Mar 18 15:31 polld
drwxr-xr-x  6 ncuser ncuser  4096 Mar 18 15:31 vendor

[root@21a1c0d253f2 OpenMSA_Adapters]# git status
On branch master
Your branch is up to date with 'origin/master'.

nothing to commit, working tree clean

[root@21a1c0d253f2 OpenMSA_Adapters]# git remote -v
origin	https://github.com/openmsa/Adapters.git (fetch)
origin	https://github.com/openmsa/Adapters.git (push)

```

There are 2 ways of updating the adapters in your MSActivator:

- automatically with the script `install_libraries.sh` 
- manually using git commands

If you haven's done any change to your local version of the code, you should use the script. 
In case you have done some changes, then the script may not be able to automatically merge the code from github into your version of the code and you will have to manually update your git repository.

Automated update
----------------

From the directory where the docker-compose file is installed, run the command below

```
docker-compose exec msa_dev /usr/bin/install_libraries.sh da
docker-compose restart msa_sms
docker-compose restart msa_api

```

The script will take care of updating your local git repository and will attempt to merge the code from the remote master branch into your code.

If there are conflicting modification, you will have to update the repository manually.

Manual update
-------------

1 - From the directory where the docker-compose file is installed, connect to the `msa_dev` container:

```
docker-compose exec msa_dev bash
```

2 - Go to the adapter git repository:

```
 cd /opt/devops/OpenMSA_Adapters/
 ```

 3 - Pull the latest code of the adapter which is always available on the master branch

 ```
 git pull origin master
 ```

Git may raise conflict related errors if you have some uncommited local changes. You need to either commit your changes (`git add ...` and `git commit ...`) or stash them (`git stash`)

4 - Set the user to `ncuser`

```
chown -R ncuser. *
```

5 - Exit the container and restart msa_api and msa_sms for the changes to be applied

```
docker-compose restart msa_sms
docker-compose restart msa_api
```