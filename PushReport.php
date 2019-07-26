<?php
/**
 * 上报接口
 * @Filename: PushReport.php 
 * @Author:   wh
 * @Date:     2019-03-02 15:27:25
 * @Description:
 */
$userId     = isset($HEADERS['uid']) ? $HEADERS['uid'] : 0;
$zzUserId   = isset($HEADERS['uuid']) ? $HEADERS['uuid'] : 0;
$deviceId   = isset($HEADERS['device_id']) ? $HEADERS['device_id'] : '';
$deviceName = isset($HEADERS['device_name']) ? $HEADERS['device_name'] : '';
$pToken     = isset($_REQUEST['ptoken']) ? $_REQUEST['ptoken'] : ''; //华为、OPPO 推送使用
$appOs      = strtolower($deviceOs) == "ios" ? 2 : 1;

// 手机平台配置
$mBrand = [
    'HUAWEI' => 'HW',
    'XIAOMI' => 'XM',
    'MEIZU'  => 'MZ',
    'OPPO'   => 'OP',
    //'VIVO'   => 'VV',
];

// 获取设备品牌信息
$deviceBrand = '';

if ($appOs == 1) {
    foreach ($mBrand as $key => $val) {
        if (strstr(strtoupper($deviceName), $key)) {
            $deviceBrand = $key;
        }
    }
} elseif ($appOs == 2) {
    $deviceBrand = 'iOS';
}

$userVip = 0;
if (!empty($userId) && !empty($zzUserId)) {
    $userInfo = UserCenterModel::getUserInfo($userId, $zzUserId);
    if (!empty($userInfo)) {
        // 获取用户VIP ID
        $userVip = $userInfo[0]['vip'];

        // 记录用户设备信息
        $fieldArr = array(
            'device_id'   => 'device_id',
            'device_name' => 'device_name',
            'token'       => 'token',
            'vip'         => 'vip',
        );
        $deviceRe = DeviceModel::getUserDevice($zzUserId, $userId, $fieldArr);
        $diffDT   = md5($deviceId . $pToken . $userVip);
        $diffDT1  = md5($deviceRe[0]['device_id'] . $deviceRe[0]['token'] . $deviceRe[0]['vip']);

        // 查询'HUAWEI', 'OPPO',当前pToken是否已经存在 存在则清空
        if (!empty($pToken) && in_array($deviceBrand, array('HUAWEI', 'OPPO'))) {
            $re      = DeviceModel::getPToken($pToken, $deviceBrand);
            $diffUA  = md5($zzUserId . $userId);
            $diffUA1 = md5($re[0]['userid'] . $re[0]['agentid']);
            if (!empty($re) && $diffUA != $diffUA1) {
                DeviceModel::delPToken($pToken, $deviceBrand);
            }
        }

        if (!empty($deviceRe) && $diffDT != $diffDT1) {
            // 更新用户设备信息
            $whereStr = "user_user_id = '{$zzUserId}' and agent_id = '{$userId}'";
            $fields   = [
                'device_id'    => $deviceId,
                'device_name'  => $deviceName,
                'device_brand' => $deviceBrand,
                'vip'          => $userVip,
                'token'        => $pToken,
                'update_time'  => date('Y-m-d H:i:s', time()),
            ];
            DeviceModel::updateUserDevice($fields, $whereStr);
        } elseif (empty($deviceRe)) {
            // 添加用户设备信息
            $args = [
                'agent_id'     => $userId,
                'user_user_id' => $zzUserId,
                'device_id'    => $deviceId,
                'device_name'  => $deviceName,
                'device_brand' => $deviceBrand,
                'vip'          => $userVip,
                'token'        => $pToken,
                'input_time'   => date('Y-m-d H:i:s', time()),
                'update_time'  => date('Y-m-d H:i:s', time()),
            ];
            $res = DeviceModel::addUserDevice($args);
        }
    }
}

// 华为、OPPO 特殊处理
if (!empty($deviceBrand) && !empty($pToken) && in_array($deviceBrand, array('HUAWEI', 'OPPO'))) {
    if ($deviceBrand == 'HUAWEI') {
        $factory = 1;
    } elseif ($deviceBrand == 'OPPO') {
        $factory = 2;
    }
    $tokenIdx = md5($pToken . $factory);

    // 添加新设备
    $hoFieldArr = array('id' => 'id', 'agent_id' => 'agent_id');
    $hoTokenRe  = DeviceModel::getHWOPPushToken($factory, $pToken, $hoFieldArr);
    if (empty($hoTokenRe)) {
        // 添加数据
        $hoArgs = [
            'factory'      => $factory,
            'agent_id'     => $userId,
            'token'        => $pToken,
            'token_idx'    => $tokenIdx,
            'input_time'   => date('Y-m-d H:i:s', time()),
            'update_time'  => date('Y-m-d H:i:s', time()),
        ];
        DeviceModel::addHWOPPushToken($hoArgs);
    } elseif ($hoTokenRe[0]['agent_id'] != $userId) {
        // 更新数据
        $hoWhereStr = " token_idx = '{$tokenIdx}' and is_del = 0";
        $hoFields   = [
            'agent_id'    => $userId,
            'token_idx'   => $tokenIdx,
            'update_time' => date('Y-m-d H:i:s', time()),
        ];
        DeviceModel::updateHWOPPushToken($hoFields, $hoWhereStr);
    }
}