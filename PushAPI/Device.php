<?php
/**
 * Model
 * @FileName: Device.php
 * @Author:   wh
 * @Date:     2019-01-24 12:31:43
 * @Description:
 */
class DeviceModel
{
	/**
     * 生成用户推送标签、别名
     * @param string $fTag    厂商标签 'HW', 'XM', 'MZ', 'OP', 'VV', 'IGT', 'JG'
     * @param string $token   用户TOKEN
     * @param int    $userVip 用户VIP ID
     * @param int    $agentId 代理商ID
     * @param int    $appOs   1:Android 2:IOS
     * @return
     */
    public static function getUserPushTag($fTag, $token, $userVip, $agentId, $appOs = 1)
    {
        if (empty($fTag)) {
            return false;
        }

        // 用户别名
        if ($appOs == 1) {
            $pushsTagUser = !empty($token) ? substr(md5($token . 'PS'), 8, 16) : '';
        } elseif ($appOs == 2) {
            $pushsTagUser = !empty($token) ? substr(md5($token . 'PS' . 'iOS'),8,16) : '';
        } else {
            $pushsTagUser = '';
        }

        if (!empty($token) && !empty($userVip) && !empty($agentId)) {
            // 区域代理商VIP用户标签
            if ($appOs == 1) {
                $pushsTagAreaIsVip = substr(md5($agentId . 'PSVip' . $fTag), 8, 16);
            } elseif ($appOs == 2) {
                $pushsTagAreaIsVip = substr(md5($agentId . 'PSVip' . $fTag . 'iOS'), 8, 16);
            } else {
                $pushsTagAreaIsVip = '';
            }

        } elseif (!empty($token) && !empty($agentId)) {
            // 区域代理商普通用户标签
            if ($appOs == 1) {
                $pushsTagAreaIsVip = substr(md5($agentId . 'PSNoVip' . $fTag), 8, 16);
            } elseif ($appOs == 2) {
                $pushsTagAreaIsVip = substr(md5($agentId . 'PSNoVip' . $fTag . 'iOS'), 8, 16);
            } else {
                $pushsTagAreaIsVip = '';
            }

        } else {
            $pushsTagAreaIsVip = '';
        }

        if (!empty($token) && !empty($userVip)) {
            // 全平台VIP用户标签
            if ($appOs == 1) {
                $pushsTagIsVip = substr(md5('PSVip' . $fTag), 8, 16);
            } elseif ($appOs == 2) {
                $pushsTagIsVip = substr(md5('PSVip' . $fTag . 'iOS'), 8, 16);
            } else {
                $pushsTagIsVip = '';
            }

        } elseif (!empty($token)) {
            // 全平台普通用户标签
            if ($appOs == 1) {
                $pushsTagIsVip = substr(md5('PSNoVip' . $fTag), 8, 16);
            } elseif ($appOs == 2) {
                $pushsTagIsVip = substr(md5('PSNoVip' . $fTag . 'iOS'), 8, 16);
            } else {
                $pushsTagIsVip = '';
            }

        } else {
            $pushsTagIsVip = '';
        }

        // 区域代理商用户标签
        if ($appOs == 1) {
            $pushsTagArea = !empty($agentId) ? substr(md5($agentId . 'PSArea' . $fTag), 8, 16) : '';
        } elseif ($appOs == 2) {
            $pushsTagArea = !empty($agentId) ? substr(md5($agentId . 'PSArea' . $fTag . 'iOS'), 8, 16) : '';
        } else {
            $pushsTagArea = '';
        }

        // 全平台所有用户标签
        if ($appOs == 1) {
            $pushsTagAll = substr(md5('PSAll' . $fTag), 8, 16);
        } elseif ($appOs == 2) {
            $pushsTagAll = substr(md5('PSAll' . $fTag . 'iOS'), 8, 16);
        } else {
            $pushsTagAll = '';
        }

        $pushData = array(
            'pushU'   => $pushsTagUser,
            'pushAIV' => $pushsTagAreaIsVip,
            'pushIV'  => $pushsTagIsVip,
            'pushA'   => $pushsTagArea,
            'pushAll' => $pushsTagAll,
        );
        return $pushData;
    }

    /**
     * 获取用户设备信息
     * @param int   $userId  用户ID
     * @param int   $agentId 代理商ID
     * @param array $field   字段
     * @param bool  $debug
     * @return
     */
    public static function getUserDevice($userId, $agentId, $field, $debug = false)
    {
        if (empty($userId) || empty($agentId) || empty($field)) {
            return false;
        }
        $whereStr = " d.user_user_id = {$userId} and d.agent_id = {$agentId}";

        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));
        $DBHelper->from('device_info d', $field);
        $DBHelper->addAndWhere($whereStr);
        $DBHelper->debug = $debug ? 1 : 0;

        $query = $DBHelper->query();
        return !empty($query) ? PublicModel::objToArray($DBHelper->query()) : false;
    }

    /**
     * 查询指定pToken
     * @param string $pToken      推送token
     * @param string $deviceBrand 厂商
     * @return
     */
    public static function getPToken($pToken, $deviceBrand, $debug = false)
    {
        if (empty($pToken) || empty($deviceBrand)) {
            return false;
        }

        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));
        $fields   = array(
            'userid'  => 'user_user_id',
            'agentid' => 'agent_id',
        );
        $DBHelper->from('device_info d', $fields);
        $whereStr = "token = '{$pToken}' && device_brand = '{$deviceBrand}'";
        $DBHelper->addAndWhere($whereStr);
        $DBHelper->debug = $debug ? 1 : 0;

        $query = $DBHelper->query();
        return !empty($query) ? Model_Public::objToArray($query) : false;
    }

    /**
     * 清空指定pToken数据
     * @param string $pToken      推送token
     * @param string $deviceBrand 厂商
     * @return
     */
    public static function delPToken($pToken, $deviceBrand, $debug = false)
    {
        if (empty($pToken) || empty($deviceBrand)) {
            return false;
        }

        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));
        $DBHelper->from('device_info d', array('id' => 'id'));

        $whereStr = "token = '{$pToken}' && device_brand = '{$deviceBrand}'";
        $DBHelper->debug = $debug ? 1 : 0;

        return $DBHelper->update('device_info', array('token' => ''), $whereStr);
    }

    /**
     * 更新用户设备信息
     * @param array  $field 字段
     * @param string $where 条件
     * @param bool   $debug
     * @return
     */
    public static function updateUserDevice($field, $where, $debug = false)
    {
        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));

        $DBHelper->debug = $debug ? 1 : 0;
        return $DBHelper->update('device_info', $field, $where);
    }

    /**
     * 添加用户设备信息
     * @param array $args  参数
     * @param bool  $debug
     * @return
     */
    public static function addUserDevice($args, $debug = false)
    {
        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));

        $DBHelper->debug = $debug ? 1 : 0;
        return $DBHelper->insert('device_info', $args);
    }

    /**
     * 获取华为、OPPO 厂商推送TOKEN信息
     * @param int    $factory 华为 1、OPPO 2
     * @param string $pToken  华为、OPPO 厂商推送TOKEN
     * @param array  $field   字段
     * @param bool   $debug
     * @return
     */
    public static function getHWOPPushToken($factory, $pToken, $field, $debug = false)
    {
        if (empty($pToken) && !in_array($factory, array(1, 2))) {
            return false;
        }
        $token_idx = md5($pToken . $factory);
        $whereStr = " d.is_del = 0 and d.token_idx = '{$token_idx}'";

        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));
        $DBHelper->from('device_by_hwop d', $field);
        $DBHelper->addAndWhere($whereStr);
        $DBHelper->debug = $debug ? 1 : 0;

        $query = $DBHelper->query();
        $re    = !empty($query) ? Model_Public::objToArray($DBHelper->query()) : false;
        return $re;
    }

    /**
     * 添加华为、OPPO 厂商推送TOKEN信息
     * @param array $args  参数
     * @param bool  $debug
     * @return
     */
    public static function addHWOPPushToken($args, $debug = false)
    {
        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));

        $DBHelper->debug = $debug ? 1 : 0;
        return $DBHelper->insert('device_by_hwop', $args);
    }

    /**
     * 更新华为、OPPO 厂商推送TOKEN信息
     * @param array  $field 字段
     * @param string $where 条件
     * @param bool   $debug
     * @return
     */
    public static function updateHWOPPushToken($field, $where, $debug = false)
    {
        $DBHelper = Factory::N('DBHelper', Ebase::getDb('DB_Rds_**'));

        $DBHelper->debug = $debug ? 1 : 0;
        return $DBHelper->update('device_by_hwop', $field, $where);
    }
}
