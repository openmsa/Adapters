#!/bin/bash
set -e
error() {
	echo "🎃 Failed !!!"
}
trap error ERR

log_info() {
    TIMESTAMP=$(date --iso-8601=ns)
    echo -e "$TIMESTAMP | $*"
}

if [[ -n "$ENABLE_K8S" ]];
then
	log_info "⏳ Wait for linuxdev."
	until nc -zv msa-dev 22; do
		sleep 10
		log_info "⏳ Wait for linuxdev."
	done
fi
cd / || exit 1

if [[ -f /opt/devops/OpenMSA_Adapters/.devops ]]; then
	log_info "👾 Skipping upgrade for fellow developer."
	exit 0
fi
tar --overwrite --no-same-owner -xf /home/ncuser/devops.tar.xz -I 'xz -T0' --checkpoint=1000 --checkpoint-action=echo='%{%Y-%m-%d %H:%M:%S}t⏳ \033[1;37m(%d sec)\033[0m: \033[1;32m#%u\033[0m, \033[0;33m%{}T\033[0m'
echo "✅ Sucess ..."


