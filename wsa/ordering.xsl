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
          <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
      </xsl:for-each>
      <xsl:copy-of select="ldap_query"/>
      <xsl:copy-of select="ldap_interface"/>
      <xsl:copy-of select="internal_ldap"/>
      <xsl:copy-of select="ldap_silent_group_failures"/>
      <xsl:copy-of select="certificate_name"/>
    </xsl:element>
  </xsl:template>


  <xsl:template match="/config/wga_config/prox_acl_policy_groups/prox_acl_group">
    <xsl:element name="prox_acl_group">
      <xsl:copy-of select="prox_acl_group_id"/>
      <xsl:copy-of select="prox_acl_group_description"/>
      <xsl:copy-of select="prox_acl_group_enabled"/>
      <xsl:copy-of select="prox_acl_group_membership_customcats"/>
      <xsl:copy-of select="prox_acl_group_connect_ports"/>
      <xsl:copy-of select="prox_acl_group_customcat_actions"/>
      <xsl:copy-of select="prox_acl_group_customcat_redirects"/>
      <xsl:copy-of select="prox_acl_group_webcat_actions"/>
      <xsl:copy-of select="prox_acl_group_firestone_actions"/>
      <xsl:copy-of select="prox_acl_group_avc_actions"/>
      <xsl:copy-of select="prox_acl_group_protocols"/>
      <xsl:copy-of select="prox_acl_group_file_types"/>
      <xsl:copy-of select="prox_acl_group_file_extensions"/>
      <xsl:copy-of select="prox_acl_group_malware_verdicts"/>
      <xsl:copy-of select="prox_acl_group_custom_user_agents"/>
      <xsl:copy-of select="prox_acl_group_wbrs_enabled"/>
      <xsl:copy-of select="prox_acl_group_wbrs_block_below"/>
      <xsl:copy-of select="prox_acl_group_wbrs_allow_above"/>
      <xsl:copy-of select="prox_acl_group_wbrs_no_score_action"/>
      <xsl:copy-of select="prox_acl_group_adaptive_scanning_block"/>
      <xsl:copy-of select="prox_acl_group_identities"/>
    </xsl:element>
  </xsl:template>


  <xsl:template match="/config/wga_config/prox_config_authentication_mode">
    <xsl:choose>
      <xsl:when test="count(/config/wga_config/prox_config_auth_realms/prox_config_auth_realm/prox_config_auth_realm_ntlm) &gt; 0">
        <xsl:element name="prox_config_authentication_mode"><xsl:text>NTLMBASIC_NTLMSSP</xsl:text></xsl:element>
      </xsl:when>
      <xsl:otherwise>
        <xsl:element name="prox_config_authentication_mode"><xsl:text>Disabled</xsl:text></xsl:element>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>


  <xsl:template match="/config/ntp">
    <xsl:element name="ntp">
      <xsl:for-each select="ntp_server">
        <xsl:copy>
          <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
      </xsl:for-each>
      <xsl:copy-of select="ntp_routing_table"/>
    </xsl:element>
  </xsl:template>


  <xsl:template match="@* | node()">
    <xsl:copy>
      <xsl:apply-templates select="@* | node()"/>
    </xsl:copy>
  </xsl:template>


</xsl:stylesheet>

