<?php
/*
 *  Available global variables
 *   $sms_sd_info       sd_info structure
 *   $sdid
 *   $sms_module        module name (for patterns)
 *   $sd_poll_elt       pointer on sd_poll_t structure
 *   $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Asset management
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once load_once('aws_generic', 'aws_generic_connect.php');
require_once "$db_objects";

try
{
  // Connection
  aws_connect();

  $asset = array();
  $asset_attributes = array();
  
/**
listSSHPublicKeys

[
    'IsTruncated' => true || false,
    'Marker' => '<string>',
    'SSHPublicKeys' => [
        [
            'SSHPublicKeyId' => '<string>',
            'Status' => 'Active|Inactive',
            'UploadDate' => <DateTime>,
            'UserName' => '<string>',
        ],
        // ...
    ],
]

listServerCertificates

[
    'IsTruncated' => true || false,
    'Marker' => '<string>',
    'ServerCertificateMetadataList' => [
        [
            'Arn' => '<string>',
            'Expiration' => <DateTime>,
            'Path' => '<string>',
            'ServerCertificateId' => '<string>',
            'ServerCertificateName' => '<string>',
            'UploadDate' => <DateTime>,
        ],
        // ...
    ],
]

listSigningCertificates

[
    'Certificates' => [
        [
            'CertificateBody' => '<string>',
            'CertificateId' => '<string>',
            'Status' => 'Active|Inactive',
            'UploadDate' => <DateTime>,
            'UserName' => '<string>',
        ],
        // ...
    ],
    'IsTruncated' => true || false,
    'Marker' => '<string>',
]

listUsers

[
    'IsTruncated' => true || false,
    'Marker' => '<string>',
    'Users' => [
        [
            'Arn' => '<string>',
            'CreateDate' => <DateTime>,
            'PasswordLastUsed' => <DateTime>,
            'Path' => '<string>',
            'UserId' => '<string>',
            'UserName' => '<string>',
        ],
        // ...
    ],
]

*/

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "Aws\Iam\IamClient#listAccessKeys#");
  $buffer = $sms_sd_ctx->get_raw_json();
  $buffer = json_decode($buffer, true);

  foreach ($buffer['AccessKeyMetadata'] as $accessMetaData)
  {
  	foreach ($accessMetaData as $key => $value) {
  		
  	  echo "$key => $value\n";
      $asset[$key] = $value;
      $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $key, $value);
    }
  }
  
  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "Aws\Iam\IamClient#listAccountAliases#");
  $buffer = $sms_sd_ctx->get_raw_json();
  $buffer = json_decode($buffer, true);
  
  $aliasCount = 1;
  foreach ($buffer['AccountAliases'] as $value)
  {
  		$key = "AccountAlias-{$aliasCount}";
  		$asset[$key] = $value;
  		$ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $key, $value);
  		$aliasCount++;
  }

  sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "Aws\Iam\IamClient#getAccountSummary#");
  $buffer = $sms_sd_ctx->get_raw_json();
  $buffer = json_decode($buffer, true);
  
  foreach ($buffer['SummaryMap'] as $key => $value)
  {
  	  echo "$key => $value\n";
      $asset[$key] = $value;
      $ret = sms_sd_set_asset_attribute($sd_poll_elt, 1, $key, $value);
  }
  
  /*****/
  $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
  if ($ret !== 0)
  {
    debug_dump($asset, "Asset failed:\n");
    throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
  }

  aws_disconnect();
}

catch (Exception | Error $e)
{
  aws_disconnect();
  debug_dump($asset, "Asset failed:\n");
  throw new SmsException(" sms_polld_set_asset_in_sd Failed", ERR_DB_FAILED);
}

return SMS_OK;

?>