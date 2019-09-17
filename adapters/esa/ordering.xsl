<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="xml"
      media-type="config"
      doctype-system="config.dtd"
      encoding="iso-8859-1"
      indent="yes"
      omit-xml-declaration="no" />

	<xsl:template match="/config/ldap">
		<xsl:element name="ldap">
			<!-- Show all ldap_server -->
			<xsl:for-each select="ldap_server">
				<xsl:copy>
					<xsl:apply-templates select="@* | node()" />
				</xsl:copy>
			</xsl:for-each>
        <xsl:copy-of select="ldap_query"/>
		<xsl:copy-of select="ldap_interface" />
        <xsl:copy-of select="internal_ldap"/>
		<xsl:copy-of select="ldap_silent_group_failures" />
        <xsl:copy-of select="certificate_name"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="/config/perrcpt_policies/inbound_policies/policy">
		<xsl:element name="policy">
			<xsl:apply-templates select="policy_name" />
			<xsl:apply-templates select="policy_member" />
			<xsl:apply-templates select="@* | node()" mode="inbound_policies" />
		</xsl:element>
	</xsl:template>

	<xsl:template match="policy_name" mode="inbound_policies" />
	<xsl:template match="policy_member" mode="inbound_policies" />

	<xsl:template match="@* | node()" mode="inbound_policies">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()" />
		</xsl:copy>
	</xsl:template>

	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()" />
		</xsl:copy>
	</xsl:template>


</xsl:stylesheet>

