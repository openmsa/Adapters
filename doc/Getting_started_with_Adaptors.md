# MSA-Device-Adaptors

The MSActivator(TM) is a multi-tenant, full lifecycle management framework developed for agile service
design and assurance, making automation not only possible - but easy.

### Device Adaptors

The MSActivator comes with several pre-built device adaptors providing all the necessary functionality lifecycle management from provisioning to image and asset management. Adaptors provide the necessary interface to communicate with different devices. 

### Installation

To install a provided (existing) device adaptor, place the relevant code in the below directory on your container msa_dev of your MSActivator:

```
quickstart$ docker-compose exec msa_dev bash
[root@1fdab2836ca0 /]# cd /opt/devops/OpenMSA_Adapters/
[root@1fdab2836ca0 OpenMSA_Adapters]# 
```

### Customize device adaptors

#### Deactivate automatic backup
A configuration variable can be set at the managed entity (ME) level to stop the automatique configuration backup each time the MSActivator(TM) is applying a configuration change (push_config). 
If a ME has got a configuration variable name ***NO_AUTOMATIC_BACKUP_ON_APPLY_CONF*** equal to ***"true"***, the MSActivator(TM) is no more collecting the device configuration automaticaly. This will allow to accelarate the configutation change process as well as sparing number of revisions in the configuration change management DB (history tab at ME level). Backup of the ME's configuration still available by running it manualy from UI or API.
