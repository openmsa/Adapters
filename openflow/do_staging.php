<?php

require_once 'smsd/sms_user_message.php';
require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';
require_once "$db_objects";

try
{
        $net_conf = get_network_profile();
        $sd = $net_conf->SD;

        $id = $sd->SDID;
        $dpid = $sd->SD_DPID;
        $dpidformated = str_replace(":","",$dpid);
        echo var_dump($_SERVER);
        $configuration = '#On your openvswitch host : <br>';
        $configuration .= '#Create the openvswitch<br>';
        $configuration .= 'ovs-vsctl add-br ' . $id . '<br>';
        $configuration .= '#Set its dpid : <br>';
        $configuration .= 'ovs-vsctl set Bridge ' . $id . ' other_config:datapath-id=' . $dpidformated . '<br>';
        $configuration .= '#Link it to the MSA :<br>';
        $configuration .= 'ovs-vsctl set-controller ' . $id . ' tcp:' . $_SERVER['SMS_ADDRESS_IP'] . ':6633<br>';

        $result = sms_user_message_add("", SMS_UMN_CONFIG, $configuration);
        $user_message = sms_user_message_add("", SMS_UMN_STATUS, SMS_UMV_OK);
        $user_message = sms_user_message_add_json($user_message, SMS_UMN_RESULT, $result);

        sms_send_user_message($sms_csp, $sdid, $user_message);
}
catch(Exception $e)
{
        sms_send_user_error($sms_csp, $sdid, $e->getMessage(), $e->getCode());
}

return SMS_OK;

?>