<?php

require_once "smserror/sms_error.php";
require_once "smsd/sms_common.php";
require_once 'smsd/expect.php';

require_once "$db_objects";

define('NEC_ERROR', 1);
define('NEC_INFO', 2);
define('NEC_DEBUG', 3);

function nec_intersecvmsg_exec_remote($in_file_line, $in_ssh, $in_cmd)
{
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_exec_remote');
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'in_file_line:' . print_r($in_file_line, true));
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'in_ssh:' . print_r($in_ssh, true));
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'in_cmd:' . print_r($in_cmd, true));
  
  // 改行のみはスキップする
  if ($in_cmd == "")
  {
    return SMS_OK;
  }

  // SSHコネクションは無効か？
  if ($in_ssh == null)
  {
    return ERR_LOCAL_SSH;
  }

  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_exec_remote:sendCmd');
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'in_file_line:' . print_r($in_file_line, true));
  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'in_cmd:' . print_r($in_cmd, true));

  try
  {
    // コマンド実行
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'START nec_intersecvmsg_exec_remote:sendexpectone');
    $buffer = sendexpectone(__FILE__.':'.__LINE__, $in_ssh, $in_cmd, '$');
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'buffer:' . print_r($buffer, true));
  }
  catch (Exception $e)
  {
    // rebootコマンドの結果がセッション断の場合、エラーとせずに処理を継続する
    if (preg_match("/reboot/", $in_cmd) && $e->getCode() === ERR_SD_CONN_CLOSED_BY_PEER)
    {
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'SMS_OUTPUT_BUF:' . print_r($SMS_OUTPUT_BUF, true));
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'ret:' . print_r($e->getCode(), true));
    }
    // 上記以外の例外はthrowする
    else
    {
      throw $e;
    }
  }
  
  // rebootコマンド以外の場合、コマンド実行結果を確認する。
  if (!preg_match("/reboot/", $in_cmd)) {
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'check cmd result');
  
    $result_buffer = sendexpectone(__FILE__.':'.__LINE__, $in_ssh, "echo $?", '$');
    $buffer = $buffer . $result_buffer;
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'buffer:' . print_r($buffer, true));
    nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'result_buffer:' . print_r($result_buffer, true));
    // コマンドの実行結果を判定する
    if (!preg_match("/^\n0/", $result_buffer)) {
      // 正常終了"0"以外の場合
      nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'Command execution result is NG');
      throw new SmsException('Nec_IntersecVMSG Command Execution Error', $buffer);
    }
  
  }

  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_exec_remote:sendCmd');

  nec_intersecvmsg_dbg_print(NEC_DEBUG, __FILE__ . ':' . __LINE__, 'END nec_intersecvmsg_exec_remote');
  
  return SMS_OK;
}

function nec_intersecvmsg_dbg_print($in_level, $in_src_line, $in_data)
{
  // 出力レベル（デバッグ用）
  $out_level = NEC_DEBUG;

  // 出力ファイル名
  $out_file = '/tmp/php_dbg.log';

  // 入力パラメータチェック
  if (($in_level != NEC_ERROR) && ($in_level != NEC_INFO) && ($in_level != NEC_DEBUG))
  {
    // 未サポートの種別を検出
    return;
  }

  // 出力レベルの判定
  if ($out_level >= $in_level)
  {
    // 出力情報の編集
    $out_data = date('Y/m/d H:i:s ') . $in_src_line . ' ' . $in_data . "\n"; 

    // ファイルへ出力
    file_put_contents($out_file, $out_data, FILE_APPEND);
  }

  // リターン
  return;
}

?>
