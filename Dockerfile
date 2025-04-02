# vim:fileencoding=utf-8:foldmethod=marker
FROM docker.io/ubiqube/msa2-linuxdev:latest AS builder
USER 1000
WORKDIR /home/ncuser

ENV BIN_DIR=/opt/fmc_repository/Process/PythonReference/bin
RUN install_default_dirs.sh

# Install Adapter {{{
COPY --chown=1000:1000 . /opt/devops/OpenMSA_Adapters
RUN install_repo_deps.sh /opt/devops/OpenMSA_Adapters/

# Cleanup repository {{{
RUN rm -rf /opt/devops/OpenMSA_Adapters/{.git,docker,Dockerfile}
# }}}
# Build tarball {{{
RUN echo "⏳ Creating devops.tar.xz" && \
    chmod a+w -R /opt/devops/ && \
    tar cf devops.tar.xz --exclude-vcs /opt/devops/ -I 'xz -T0' --checkpoint=1000 --checkpoint-action=echo='%{%Y-%m-%d %H:%M:%S}t ⏳ \033[1;37m(%d sec)\033[0m: \033[1;32m#%u\033[0m, \033[0;33m%{}T\033[0m'
# }}}

FROM docker.io/ubiqube/ubi-almalinux9:latest
# Copy all resources to the final image {{{
RUN mkdir -p /opt/devops && chown -R 1000:1000 /opt/devops
USER 1000
COPY --from=builder /home/ncuser/*.xz /home/ncuser/
COPY docker-entrypoint.sh /

ENTRYPOINT ["/docker-entrypoint.sh"]
# }}}
