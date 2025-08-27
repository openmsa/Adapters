#!/bin/bash

. /usr/share/install-libraries/il-lib.sh

pushd /opt/devops/OpenMSA_Adapters || exit

emit_step "Adapter from OpenMSA_Adapters"
chown -R ncuser:ncuser /opt/devops/
popd || exit
