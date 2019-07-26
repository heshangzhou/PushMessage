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
}
