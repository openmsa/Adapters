<?php
/*
 * Version: $Id: nec_intersecvmsg_command.php 112 2016-12-06 15:36:32Z domeki $
* Created: Apr 28, 2011
* Available global variables
*  $sms_csp            pointer to csp context to send response to user
* $sms_sd_ctx         pointer to sd_ctx context to retreive usefull field(s)
* $SMS_RETURN_BUF     string buffer containing the result
*/

require_once 'smsd/sms_common.php';
require_once 'smsd/generic_command.php';

require_once load_once('nec_intersecvmsg', 'adaptor.php');
require_once load_once('nec_intersecvmsg', 'nec_intersecvmsg_common.php');

class nec_intersecvmsg_command extends generic_command
{

  function __construct()
  {
    parent::__construct ();
    $this->parsed_objects = array ();
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command()');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'parser_list:' . print_r($this->parser_list, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'create_list:' . print_r($this->create_list, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'delete_list:' . print_r($this->delete_list, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'list_list:' . print_r($this->list_list, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'read_list:' . print_r($this->read_list, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'update_list:' . print_r($this->update_list, true));
  }

  /*
   * #####################################################################################
  * IMPORT
  * #####################################################################################
  */

  /**
   * IMPORT configuration from router
   * @param object $json_params JSON parameters of the command
   * @param domElement $element XML DOM element of the definition of the command
   */
  function eval_IMPORT()
  {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command:eval_IMPORT');

    global $sms_sd_ctx;
    global $SMS_RETURN_BUF;

    $ret = SMS_OK;
    $SMS_RETURN_BUF = '';

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'sms_sd_ctx:' . print_r($sms_sd_ctx, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'SMS_RETURN_BUF:' . print_r($SMS_RETURN_BUF, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_command:eval_IMPORT');

    return $ret;
  }

 /*
  * #####################################################################################
  * CREATE
  * #####################################################################################
  */

  /**
   * Apply created object to device and if OK add object to the database.
   */
  function apply_device_CREATE($params)
  {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command:apply_device_CREATE');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'params:' . print_r($params, true));

    $ret = $this->nec_intersecvmsg_exec_cmd($params);

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_command:apply_device_CREATE');

    return $ret;
  }

 /*
  * #####################################################################################
  * UPDATE
  * #####################################################################################
  */

  /**
   * Apply updated object to device and if OK add object to the database.
   */
  function apply_device_UPDATE($params)
  {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command:apply_device_UPDATE');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'params:' . print_r($params, true));

    $ret = $this->nec_intersecvmsg_exec_cmd($params);

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_command:apply_device_UPDATE');

    return $ret;
  }

 /*
  * #####################################################################################
  * DELETE
  * #####################################################################################
  */

  /**
   * Apply deleted object to device and if OK add object to the database.
   */
  function apply_device_DELETE($params)
  {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command:apply_device_DELETE');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'params:' . print_r($params, true));

    $ret = $this->nec_intersecvmsg_exec_cmd($params);

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_command:apply_device_DELETE');

    return $ret;
  }

  function nec_intersecvmsg_exec_cmd($params)
  {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_command:nec_intersecvmsg_exec_cmd');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'params:' . print_r($params, true));

    global $sms_sd_ctx;
    debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'sms_sd_ctx:' . print_r($sms_sd_ctx, true));

    try
    {
      // SSH接続
      $ret = nec_intersecvmsg_connect(null ,null ,null ,null, '18022');
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));

      if ($ret != SMS_OK)
      {
        nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'Nec_IntersecVMSG Connect Error ret:' . print_r($ret, true));
        //接続エラー検出
        throw new SmsException('Nec_IntersecVMSG Connect Error', $ret);
      }

      // XML内のコマンド取得
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'configuration:' . print_r($this->configuration, true));
      $tmpList = explode("\n", $this->configuration);
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'tmpList:' . print_r($tmpList, true));

      // コマンド数分ループ
      foreach ($tmpList as $cmdname)
      {
        // コマンド実行
        $ret = nec_intersecvmsg_exec_remote(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmdname);
        nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'cmdname:' . print_r($cmdname, true));
        nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));

        if ($ret != SMS_OK)
        {
          nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'Nec_IntersecVMSG Command Error ret:' . print_r($ret, true));
          // コマンド実行エラー検出
          throw new SmsException('Nec_IntersecVMSG Command Error', $ret);
        }
      }
    }
    catch (Exception $e)
    {
      // エラー情報を設定
      $SMS_OUTPUT_BUF = $e->getMessage();
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'SMS_OUTPUT_BUF:' . print_r($SMS_OUTPUT_BUF, true));

      // リターン値に、エラーコードを設定
      $ret = $e->getCode();
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));
    }

    // 処理結果は正常終了か？
    if ($ret == SMS_OK)
    {
      // 出力エリアに空文字列を設定
      $SMS_RETURN_BUF = '';
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'SMS_RETURN_BUF:' . print_r($SMS_RETURN_BUF, true));
    }

    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($ret, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_command:nec_intersecvmsg_exec_cmd');

    return $ret;
  }

}

?>
