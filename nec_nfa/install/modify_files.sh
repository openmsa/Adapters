#!/bin/sh

DEBUG=$1
NOW=$(date +"%Y%m%d%H%M")

grep "^70000," /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties"
  /bin/cp -p /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties.org.necNfa.$NOW

  ## ファイル末尾に改行がない場合の対処
  /bin/cat /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties | awk '{print}' > /tmp/msa.$$
  /bin/cp -p /tmp/msa.$$ /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties
  /bin/rm -f /tmp/msa.$$

  echo '70000,"NEC",1' >> /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties
fi

grep "^17111602," /opt/ubi-jentreprise/resources/templates/conf/device/models.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying /opt/ubi-jentreprise/resources/templates/conf/device/models.properties"
  /bin/cp -p /opt/ubi-jentreprise/resources/templates/conf/device/models.properties /opt/ubi-jentreprise/resources/templates/conf/device/models.properties.org.necNfa.$NOW

  ## ファイル末尾に改行がない場合の対処
  /bin/cat /opt/ubi-jentreprise/resources/templates/conf/device/models.properties | awk '{print}' > /tmp/msa.$$
  /bin/cp /tmp/msa.$$ /opt/ubi-jentreprise/resources/templates/conf/device/models.properties
  /bin/rm -f /tmp/msa.$$

  echo '17111602,70000,"NEC NFA","H",0,0,0,0,0,1,0,0,0,0,0,SR,0,0' >> /opt/ubi-jentreprise/resources/templates/conf/device/models.properties
fi

if [ ! -f "/opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties" ]; then
  echo "  Copying /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties"
  /bin/cp -p /opt/ses/templates/server_ALL/sdExtendedInfo.properties /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties
fi

grep "necNfa" /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties"
  /bin/cp -p /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties.org.necNfa.$NOW

  ## ファイル末尾に改行がない場合の対処
  /bin/cat /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties | awk '{print}' > /tmp/msa.$$
  /bin/cp /tmp/msa.$$ /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties
  /bin/rm -f /tmp/msa.$$

  echo 'sdExtendedInfo.router.70000-17111602 = necNfa' >> /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties
  echo 'sdExtendedInfo.jspType.70000-17111602 = necNfa' >> /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties
fi

if [ ! -f "/opt/ses/properties/specifics/server_ALL/manageLinks.properties" ]; then
  echo "  Copying /opt/ses/properties/specifics/server_ALL/manageLinks.properties"
  /bin/cp -p /opt/ses/templates/server_ALL/manageLinks.properties /opt/ses/properties/specifics/server_ALL/manageLinks.properties
fi

grep "necNfa" /opt/ses/properties/specifics/server_ALL/manageLinks.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying /opt/ses/properties/specifics/server_ALL/manageLinks.properties"
  /bin/cp -p /opt/ses/properties/specifics/server_ALL/manageLinks.properties /opt/ses/properties/specifics/server_ALL/manageLinks.properties.org.necNfa.$NOW

  /bin/cat /opt/ses/properties/specifics/server_ALL/manageLinks.properties \
    | sed 's/^\(siteLink.initialProv.models.*\)/\1 necNfa/' \
    | sed 's/^\(siteLink.detailedReports.models.*\)/\1 necNfa/' \
    | sed 's/^\(siteLink.displayLogs.models.*\)/\1 necNfa/' \
    | sed 's/^\(device.wizard.authentication.window.models.*\)/\1 necNfa/' \
    | sed 's/^\(device.cisco.license.manager.models.*\)/\1 necNfa/' \
    | sed 's/^\(device.wizard.managecertificate.models.*\)/\1 necNfa/' \
    | sed 's/^\(device.wizard.specific.rules.and.commands.models.*\)/\1 necNfa/' \
    | sed 's/^\(device.wizard.check.non.empty.credentials.models.*\)/\1 necNfa/' \
    | sed 's/^\(configobjectconsole.supported.models.*\)/\1 necNfa/' \
    > /tmp/msa.$$
  /bin/cp -p /tmp/msa.$$ /opt/ses/properties/specifics/server_ALL/manageLinks.properties
  /bin/rm -f /tmp/msa.$$
fi

if [ ! -f "/opt/ses/properties/specifics/server_ALL/ses.properties" ]; then
  echo "  Copying /opt/ses/properties/specifics/server_ALL/ses.properties"
  /bin/cp -p /opt/ses/templates/server_ALL/ses.properties /opt/ses/properties/specifics/server_ALL/ses.properties
fi

grep "^soc.device.supported.nec" /opt/ses/properties/specifics/server_ALL/ses.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying /opt/ses/properties/specifics/server_ALL/ses.properties"
  /bin/cp -p /opt/ses/properties/specifics/server_ALL/ses.properties /opt/ses/properties/specifics/server_ALL/ses.properties.org.necNfa.$NOW
  echo 'soc.device.supported.nec=1' >> /opt/ses/properties/specifics/server_ALL/ses.properties
fi

if [ ! -f "/opt/ses/properties/specifics/server_ALL/repository.properties" ]; then
  echo "  Copying /opt/ses/properties/specifics/server_ALL/repository.properties"
  /bin/cp -p /opt/ses/templates/server_ALL/repository.properties /opt/ses/properties/specifics/server_ALL/repository.properties
fi

CP_DONE=0
grep "^repository.manufacturer.* NEC.*" /opt/ses/properties/specifics/server_ALL/repository.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying(1) /opt/ses/properties/specifics/server_ALL/repository.properties"
  /bin/cp -p /opt/ses/properties/specifics/server_ALL/repository.properties /opt/ses/properties/specifics/server_ALL/repository.properties.org.necNfa.$NOW
  CP_DONE=1
  /bin/cat /opt/ses/properties/specifics/server_ALL/repository.properties \
    | sed 's/^\(repository.manufacturer.*\)/\1 NEC/' \
    > /tmp/msa.$$
  /bin/cp -p /tmp/msa.$$ /opt/ses/properties/specifics/server_ALL/repository.properties
  /bin/rm -f /tmp/msa.$$
fi

grep "^repository.access.nec=" /opt/ses/properties/specifics/server_ALL/repository.properties > /dev/null
if [ $? -ne 0 ]; then
  echo "  Modifying(2) /opt/ses/properties/specifics/server_ALL/repository.properties"
  if [ $CP_DONE -eq 0 ]; then
    /bin/cp -p /opt/ses/properties/specifics/server_ALL/repository.properties /opt/ses/properties/specifics/server_ALL/repository.properties.org.necNfa.$NOW
    CP_DONE=1
  fi
  echo 'repository.model.nec=70000-17072801 70000-17111601 70000-17111602' >> /opt/ses/properties/specifics/server_ALL/repository.properties
  echo 'repository.access.nec=|Configuration|Firmware|CommandDefinition|Datafiles|Reports|License|Orchestration|Process|' >> /opt/ses/properties/specifics/server_ALL/repository.properties
fi

## for debug
if [ "$DEBUG" == "debug" ]; then
  echo
  diff -u /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties.org.necNfa.$NOW /opt/ubi-jentreprise/resources/templates/conf/device/manufacturers.properties
  diff -u /opt/ubi-jentreprise/resources/templates/conf/device/models.properties.org.necNfa.$NOW /opt/ubi-jentreprise/resources/templates/conf/device/models.properties
  diff -u /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties.org.necNfa.$NOW /opt/ses/properties/specifics/server_ALL/sdExtendedInfo.properties
  diff -u /opt/ses/properties/specifics/server_ALL/manageLinks.properties.org.necNfa.$NOW /opt/ses/properties/specifics/server_ALL/manageLinks.properties
  diff -u /opt/ses/properties/specifics/server_ALL/ses.properties.org.necNfa.$NOW /opt/ses/properties/specifics/server_ALL/ses.properties
  diff -u /opt/ses/properties/specifics/server_ALL/repository.properties.org.necNfa.$NOW /opt/ses/properties/specifics/server_ALL/repository.properties
fi

exit 0

## End of File
