#!/bin/sh

service ubi-ses stop
/opt/ses/configure
service ubi-ses start
service jboss restart
service ubi-sms restart

exit 0
