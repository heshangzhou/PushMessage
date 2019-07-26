<?php
/**
 * 通过接收push_code跳转内联页
 * @Filename: PushLink.php 
 * @Author:   wh
 * @Date:     2019-03-08 16:56:09
 * @Description:
 */
$deviceId   = isset($HEADERS['device_id']) ? $HEADERS['device_id'] : '';

$push_code = $_REQUEST['push_code'] ? $_REQUEST['push_code'] : ''; #加密字符串。
if (empty($push_code)) {
    $code = 401;
} else {
    //判断code是否存在
    if (!empty($userId)) {
        $table = 'push_msg_' . $userId;
        $sql   = "select id,push_os,push_status,address,address_param from {$table} where push_code = '{$push_code}' and is_del = 0";
        $res   = PublicModel::querySql($sql, 'get_results', 'DB_Rds_**', 'O');
    } else {
        $table = 'push_msg';
        $sql   = "select id,push_os,push_status,address,address_param from {$table} where push_code = '{$push_code}' and is_del = 0";
        $res   = PublicModel::querySql($sql, 'get_results', 'DB_Rds_**', 'O');
    }

    if (!$res && empty($res[0]->push_status)) {
        $code = 700;
    } else {
        $address = !empty($res[0]->address) ? $res[0]->address : '';

        $addressParam = '';

        if (!empty($res[0]->address_param)) {
            $addressParam = json_decode($res[0]->address_param, true);
        }

        $indexData = array(
            'iosArdess'     => $res[0]->push_os == 2 ? $address : '',
            'iosParam'      => $res[0]->push_os == 2 ? (object) $addressParam : (object) '',
            'androidAdress' => $res[0]->push_os == 1 ? $address : '',
            'androidParam'  => $res[0]->push_os == 1 ? (object) $addressParam : (object) '',
        );

        //推送到达记录
        $arrivalArgs = array(
            'user_user_id' => !empty($zzUserID) ? $zzUserID : 0,
            'agent_id'     => !empty($userId) ? $userId : 0,
            'type'         => 1,
            'push_id'      => !empty($res[0]->id) ? $res[0]->id : 0,
            'app_os'       => !empty($deviceOs) ? $deviceOs : '',
            'content'      => json_encode($indexData),
            'device_name'  => $deviceName,
            'device_id'    => $deviceId,
            'input_time'   => TIME_STR,
        );
        PublicModel::add($arrivalArgs, 'push_arrival_record', 'DB_Rds_**');
    }
}

//返回和报错