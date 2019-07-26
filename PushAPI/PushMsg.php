<?php
/**
 * 推送消息封装类
 * @Filename: PushMsg.php 
 * @Author:   wh
 * @Date:     2019-01-24 13:12:51
 * @Description:
 */
class PushMsg
{
    private $mFactory = array(); // 推送厂商
    private $mBrand   = array(); // 推送厂商品牌
    public  $appOs    = 1;       // 推送目标手机系统 1:Android 2:IOS

    public function __construct($appOs = 1)
    {
        $this->appOs = $appOs == 2 ? 2 : 1;

        // $this->mFactory = array('HW', 'XM', 'MZ', 'OP', 'VV', 'IGT', 'JG');
        $this->mFactory = array('HW', 'XM', 'MZ', 'OP', 'JG');
        $this->mBrand   = array(
            'HUAWEI' => 'HW',
            'XIAOMI' => 'XM',
            'MEIZU'  => 'MZ',
            'OPPO'   => 'OP',
            //'VIVO'   => 'VV',
        );
    }

    /**
     * 单用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $userId   用户ID
     * @param int    $agentId  代理商ID
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function singleUserPush($title, $content, $userId, $agentId, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        // 获取厂商设置信息
        $fieldArr = array(
            'device_brand' => 'device_brand',
            'device_id'    => 'device_id',
            'token'        => 'token',
        );
        $deviceRe = DeviceModel::getUserDevice($userId, $agentId, $fieldArr);

        if (empty($deviceRe) || empty($deviceRe[0]['device_id'])) {
            return PushCF::pushReturn(400);
        }

        // 获取用户设备匹配厂商标记
        if (empty($this->mBrand[$deviceRe[0]['device_brand']])) {
            // 获取不到四大厂商品牌，使用极光推送标记
            $mFacTag = 'JG';
        } else {
            $mFacTag = $this->mBrand[$deviceRe[0]['device_brand']];
        }

        // 判断华为、OPPO 设备token是存在
        if (in_array($mFacTag, array('HW', 'OP')) && empty($deviceRe[0]['token'])) {
            return PushCF::pushReturn(401);
        }

        // 判断用户是否存在 并且该会员是否属于本运营商
        $userInfo = Model_User::getUser($userId, $agentId);
        if (empty($userInfo) || !isset($userInfo['vip'])) {
            return PushCF::pushReturn(402);
        }
        // 获取用户VIP
        $uVip = $userInfo['vip'];

        // 获取用户token
        $uToken = Model_HuiLife::getToken($userId, $agentId);
        if (empty($uToken)) {
            return PushCF::pushReturn(403);
        }

        // 获取华为设备token或OPPO设备ID做为别名使用
        if (in_array($mFacTag, array('HW', 'OP'))) {
            if ($this->appOs == 2) {
                return false;
            }

            $alias = $deviceRe[0]['token'];
        } else {
            // 获取用户设备匹配厂商别名
            if (in_array($mFacTag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                $aliasArr = DeviceModel::getUserPushTag($mFacTag, $uToken, $uVip, $agentId);
            } elseif ($mFacTag == 'JG' && $this->appOs == 2) {
                $aliasArr = DeviceModel::getUserPushTag($mFacTag, $uToken, $uVip, $agentId, 2);
            } else {
                return false;
            }

            if (empty($aliasArr['pushU'])) {
                return PushCF::pushReturn(404);
            }
            $alias = $aliasArr['pushU'];
        }

        // 初始化推送记录
        $acParam['msg_type']      = $pushType;
        $acParam['push_name']     = $alias;
        $acParam['action_type']   = 1; #intent操作
        $acParam['push_os']       = $this->appOs == 2 ? 2 : 1;; #1:安卓 2:IOS
        $acParam['getto_way']     = !empty($msgAc['type']) ? $msgAc['type'] : 0;
        $acParam['is_sound']      = !empty($msgAc['is_sound']) ? $msgAc['is_sound'] : 0;
        $acParam['address']       = !empty($msgAc['data']['address']) ? $msgAc['data']['address'] : '';
        $acParam['address_param'] = !empty($msgAc['data']['addressParam']) ? json_encode($msgAc['data']['addressParam']) : '';
        $acParam['factory_tag']   = $mFacTag;

        // 生成初始化PUSH CODE
        $pAcRe = DeviceModel::pushActionAdd($content, $userId, $agentId, $acParam);
        if (empty($pAcRe['push_id']) || empty($pAcRe['push_code'])) {
            return PushCF::pushReturn(405);
        }

        // 如果push_code方式推送 则写入参数push_code
        if ($acParam['getto_way'] == 1) {
            unset($msgAc['data']);
            $msgAc['push_code'] = $pAcRe['push_code'];

            if ($this->appOs == 2) {
                $msgAc['platform'] = 'ios';
            }
        }

        // 获取用户设备匹配厂商类
        $factoryObj = $this->factory($mFacTag);

        if (1 == $pushType) {
            // 透传推送
            if (in_array($mFacTag, array('MZ', 'OP'))) {
                // 魅族、OPPO转通知
                $res = $factoryObj->singleUserPush($title, $content, $alias, $msgAc);
            } else {
                $res = $factoryObj->singleUserTransPush($content, $alias, $msgAc);
            }
        } else {
            // 通知推送
            $res = $factoryObj->singleUserPush($title, $content, $alias, $msgAc);
        }

        // 更新推送记录信息
        DeviceModel::pushActionUp($pAcRe['push_id'], $pAcRe['push_code'], $res, $agentId);
        return $res;
    }

    /**
     * 多用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $userArr  用户数组 限制50个
     * @param int    $agentId  代理商ID
     * @param int    $pushType 推送类型：0通知 1透传,默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function multiuserPush($title, $content, $userArr, $agentId, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        // 获取厂商设置信息
        $fieldArr = array(
            'user_user_id' => 'user_user_id',
            'device_brand' => 'device_brand',
            'device_id'    => 'device_id',
            'token'        => 'token',
        );
        $deviceRe = DeviceModel::getUserDeviceList($userArr, $agentId, $fieldArr);
        if (empty($deviceRe)) {
            return PushCF::pushReturn(400);
        }

        $mFacTag     = '';
        $deviceUArr  = array();
        $userUserStr = '';
        foreach ($deviceRe as $key => $val) {
            if (empty($val['user_user_id'])) {
                continue;
            }
            $userUserStr .= $val['user_user_id'] . ',';
            $mFacTag = $this->mBrand[$val['device_brand']];

            // 获取用户设备信息，并且匹配厂商标记
            if (empty($mFacTag)) {
                // 获取不到四大厂商品牌，使用极光推送标记
                $deviceUArr[$val['user_user_id']] = array(
                    'tag'   => 'JG',
                    'token' => $val['token'],
                );
            } else {
                $deviceUArr[$val['user_user_id']] = array(
                    'tag'   => $mFacTag,
                    'token' => $val['token'],
                );
            }
        }
        $userUserStr = trim($userUserStr, ',');

        // 判断用户是否存在,判断用户数量是否超出限制
        $deviceUArrSum = count($deviceUArr);
        if (empty($deviceUArr) || $deviceUArrSum > 50) {
            return PushCF::pushReturn(402);
        }

        // 判断用户是否存在并且是否属于本运营商
        $mFacTagArr = array();
        $uFieldArr  = array('id' => 'id', 'vip' => 'vip');
        $userWhere  = " s.id in($userUserStr) and s.user_id = $agentId and is_del = 0";
        $userInfoRe = Model_User::getUserUserArr($userWhere, $uFieldArr);

        if (!empty($userInfoRe)) {
            foreach ($userInfoRe as $ukey => $uval) {
                if (!empty($deviceUArr[$uval['id']]['tag'])) {
                    $mFacTagArr[$deviceUArr[$uval['id']]['tag']][] = array(
                        'uuid'  => $uval['id'],
                        'uvip'  => $uval['vip'],
                        'token' => $deviceUArr[$uval['id']]['token'],
                    );
                }
            }
        }

        if (empty($mFacTagArr)) {
            return PushCF::pushReturn(402);
        }

        // 获取各个厂商别名数组
        $userAliasArr = array();
        foreach ($mFacTagArr as $mtag => $mval) {
            foreach ($mval as $v) {
                // 获取华为设备token或OPPO设备ID做为别名使用
                if (in_array($mtag, array('HW', 'OP')) && !empty($v['token'])) {
                    if ($this->appOs == 2) {
                        continue;
                    }

                    $userAliasArr[$mtag]['alias'][] = $v['token'];
                    $userAliasArr[$mtag]['uuid'][]  = array($v['token'] => $v['uuid']);
                } elseif (!empty($v['uuid'])) {
                    // 获取用户token
                    $uToken = Model_HuiLife::getToken($v['uuid'], $agentId);
                    if (!empty($uToken)) {
                        if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                            $userTagRe = DeviceModel::getUserPushTag($mtag, $uToken, $v['vip'], $agentId);
                        } elseif ($mtag == 'JG' && $this->appOs == 2) {
                            $userTagRe = DeviceModel::getUserPushTag($mtag, $uToken, $v['vip'], $agentId, 2);
                        } else {
                            continue;
                        }

                        if (!empty($userTagRe['pushU'])) {
                            $userAliasArr[$mtag]['alias'][] = $userTagRe['pushU'];
                            $userAliasArr[$mtag]['uuid'][]  = array($userTagRe['pushU'] => $v['uuid']);
                        }
                    }
                }
            }
        }

        if (empty($userAliasArr)) {
            return PushCF::pushReturn(404);
        }

        // 按厂商别名推送
        $resArr = $acParam = array();
        foreach ($userAliasArr as $atag => $aval) {
            if (empty($aval['alias']) || empty($aval['uuid'])) {
                continue;
            }

            // 初始化推送记录
            $acParam['msg_type']      = $pushType;
            $acParam['push_name']     = '';
            $acParam['action_type']   = 1; #intent操作
            $acParam['push_os']       = $this->appOs == 2 ? 2 : 1;; #1:安卓 2:IOS
            $acParam['getto_way']     = !empty($msgAc['type']) ? $msgAc['type'] : 0;
            $acParam['is_sound']      = !empty($msgAc['is_sound']) ? $msgAc['is_sound'] : 0;
            $acParam['address']       = !empty($msgAc['data']['address']) ? $msgAc['data']['address'] : '';
            $acParam['address_param'] = !empty($msgAc['data']['addressParam']) ? json_encode($msgAc['data']['addressParam']) : '';
            $acParam['factory_tag']   = $atag;
            $acParam['remarks']       = json_encode($aval['uuid']);

            // 生成初始化PUSH CODE
            $pAcRe = DeviceModel::pushActionAdd($content, '', $agentId, $acParam);
            if (empty($pAcRe['push_id']) || empty($pAcRe['push_code'])) {
                $res = PushCF::pushReturn(405, array($atag));
            } else {
                // 如果push_code方式推送 则写入参数push_code
                if ($acParam['getto_way'] == 1) {
                    unset($msgAc['data']);
                    $msgAc['push_code'] = $pAcRe['push_code'];

                    if ($this->appOs == 2) {
                        $msgAc['platform'] = 'ios';
                    }
                }

                // 获取用户设备匹配厂商类
                $factoryObj = $this->factory($atag);
                if (1 == $pushType) {
                    // 透传推送
                    if (in_array($atag, array('MZ', 'OP'))) {
                        // 魅族、OPPO转通知
                        $res = $factoryObj->multiuserPush($title, $content, $aval['alias'], $msgAc);
                    } else {
                        $res = $factoryObj->multiuserTransPush($content, $aval['alias'], $msgAc);
                    }
                } else {
                    // 通知推送
                    $res = $factoryObj->multiuserPush($title, $content, $aval['alias'], $msgAc);
                }
                // 更新推送记录信息
                DeviceModel::pushActionUp($pAcRe['push_id'], $pAcRe['push_code'], $res, $agentId);
            }
            $resArr[$atag] = $res;
        }
        return $resArr;
    }

    /**
     * 区域普通用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $agentId  代理商ID
     * @param int    $pushType 推送类型：0通知 1透传默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByAreaNoVip($title, $content, $agentId, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPPush($title, $content, $agentId, 0, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 0, $agentId);
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 0, $agentId, 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushAIV'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['agent_id']  = $agentId;
                    $pushParam['push_name'] = $userTagRe['pushAIV'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushAIV']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * 区域VIP用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $agentId  代理商ID
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByAreaVip($title, $content, $agentId, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPPush($title, $content, $agentId, 1, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 1, $agentId);
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 1, $agentId, 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushAIV'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['agent_id']  = $agentId;
                    $pushParam['push_name'] = $userTagRe['pushAIV'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushAIV']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * 区域用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $agentId  代理商ID
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByArea($title, $content, $agentId, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPAreaPush($title, $content, $agentId, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, '', '', $agentId);
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, '', '', $agentId, 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushA'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['agent_id']  = $agentId;
                    $pushParam['push_name'] = $userTagRe['pushA'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushA']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * 普通用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByNoVip($title, $content, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPPush($title, $content, 0, 0, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', '', '');
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', '', '', 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushIV'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['push_name'] = $userTagRe['pushIV'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushIV']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * VIP用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByVip($title, $content, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPPush($title, $content, 0, 1, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 1, '');
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, 'token', 1, '', 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushIV'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['push_name'] = $userTagRe['pushIV'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushIV']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * 全平台用户推送(通知/透传)
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $msgAc    操作信息
     * @return
     */
    public function userPushByAll($title, $content, $pushType = 0, $msgAc = array())
    {
        $msgAc = $this->getMsgAction($msgAc);
        if (!$msgAc) {
            return PushCF::pushReturn(408);
        }

        $pushParam = array();
        foreach ($this->mFactory as $mtag) {
            // 获取华为设备token或OPPO设备ID做为别名使用
            if (in_array($mtag, array('HW', 'OP'))) {
                if ($this->appOs == 2) {
                    $resArr[$mtag] = array();
                    continue;
                }

                // 华为 / OPPO 厂商标签推送特别处理
                $resultArr = $this->labelsHWOPAreaPush($title, $content, 0, $mtag, $pushType, $msgAc);

                $resArr[$mtag] = $resultArr;
            } else {
                if (in_array($mtag, array('XM', 'MZ', 'JG')) && $this->appOs == 1) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, '', '', '');
                } elseif ($mtag == 'JG' && $this->appOs == 2) {
                    $userTagRe = DeviceModel::getUserPushTag($mtag, '', '', '', 2);
                } else {
                    $resArr[$mtag] = array();
                    continue;
                }

                if (empty($userTagRe['pushAll'])) {
                    $res = PushCF::pushReturn(406, array($mtag));
                } else {
                    $pushParam['push_name'] = $userTagRe['pushAll'];
                    $pushParam['title']     = $title;
                    $pushParam['remarks']   = ''; #op hw token json格式记录

                    $res = $this->labelsPush($mtag, array($userTagRe['pushAll']), $content, $pushType, $pushParam, $msgAc);
                }
                $resArr[$mtag] = $res;
            }
        }
        return $resArr;
    }

    /**
     * 标签推送操作封装(通知/透传)
     * @param string $fTag     单厂商推送，例如："HW"
     * @param array  $labels   标签
     * @param string $content  内容
     * @param int    $pushType 推送类型：0通知 1透传 默认通知
     * @param array  $param    参数
     * @param array  $msgAc    操作信息
     * @return
     */
    private function labelsPush($fTag, $labels, $content, $pushType = 0, $param, $msgAc)
    {
        if (empty($fTag) || empty($labels) || empty($content)) {
            return PushCF::pushReturn(405, array($fTag));
        }

//        // @TODO wh temp 测试使用
//        if (in_array($fTag, array('XM', 'MZ', 'JG'))) {
//            echo "$fTag -> {$labels[0]} \n";
//        }

        $acParam['msg_type']      = $pushType;
        $acParam['push_name']     = !empty($param['push_name']) ? $param['push_name'] : '';
        $acParam['action_type']   = 1; #intent操作
        $acParam['push_os']       = $this->appOs == 2 ? 2 : 1;; #1:安卓 2:IOS
        $acParam['getto_way']     = !empty($msgAc['type']) ? $msgAc['type'] : 0;
        $acParam['is_sound']      = !empty($msgAc['is_sound']) ? $msgAc['is_sound'] : 0;
        $acParam['address']       = !empty($msgAc['data']['address']) ? $msgAc['data']['address'] : '';
        $acParam['address_param'] = !empty($msgAc['data']['addressParam']) ? json_encode($msgAc['data']['addressParam']) : '';
        $acParam['factory_tag']   = $fTag;
        $acParam['remarks']       = !empty($param['remarks']) ? $param['remarks'] : '';

        $agentId = !empty($param['agent_id']) ? $param['agent_id'] : '';
        $title   = !empty($param['title']) ? $param['title'] : '精彩生活！上惠生活APP';

        // 生成初始化PUSH CODE
        $pAcRe = DeviceModel::pushActionAdd($content, '', $agentId, $acParam);

        if (empty($pAcRe['push_id']) || empty($pAcRe['push_code'])) {
            return PushCF::pushReturn(405, array($fTag));
        } else {
            // 如果push_code方式推送 则写入参数push_code
            if ($acParam['getto_way'] == 1) {
                unset($msgAc['data']);
                $msgAc['push_code'] = $pAcRe['push_code'];

                if ($this->appOs == 2) {
                    $msgAc['platform'] = 'ios';
                }
            }

            // 获取用户设备匹配厂商类
            $factoryObj = $this->factory($fTag);
            if (1 == $pushType) {
                // 透传推送
                if (in_array($fTag, array('MZ', 'OP'))) {
                    // 魅族、OPPO转通知
                    $res = $factoryObj->labelsPush($title, $content, $labels, $msgAc);
                } else {
                    $res = $factoryObj->labelsTransPush($content, $labels, $msgAc);
                }
            } else {
                // 通知推送
                $res = $factoryObj->labelsPush($title, $content, $labels, $msgAc);
            }

            // 更新推送记录信息
            DeviceModel::pushActionUp($pAcRe['push_id'], $pAcRe['push_code'], $res, $agentId);
            return $res;
        }
    }

    /**
     * 华为 / OPPO 标签（区域普通、区域VIP、全平台普通、全平台vip）推送处理
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $agentId  平台id
     * @param int    $vip      vip条件 0:非vip 1:vip
     * @param string $fTag     单厂商推送，例如："HW"
     * @param int    $pushType 推送类型：0通知 1透传
     * @param array  $msgAc    操作信息
     * @return
     */
    private function labelsHWOPPush($title, $content, $agentId, $vip, $fTag, $pushType, $msgAc)
    {
        $pushParam['push_name'] = '';
        $pushParam['title']     = $title;
        $pushParam['agent_id']  = $agentId;

        // 查询设备表中本平台下的所有用户数量
        $deviceCount = DeviceModel::getDeviceInfoList($agentId, $fTag == 'HW' ? 'HUAWEI' : "OPPO", $vip, array('iscnt' => 1));

        if (empty($deviceCount)) {
            $res = PushCF::pushReturn(402, array($fTag));
            return array($res);
        }

        $size = $fTag == 'HW' ? 100 : 1000;
        $page = ceil($deviceCount / $size);

        for ($i = 0; $i < $page; $i++) {
            // 查询出当前分页数据
            $param    = array('limit' => array($i * $size, $size));
            $deviceRe = DeviceModel::getDeviceInfoList($agentId, $fTag == 'HW' ? 'HUAWEI' : "OPPO", $vip, $param);

            foreach ($deviceRe as $val) {
                $pTokenArr[$fTag][$i][] = $val['token'];
                $deviceReArr['alias'][] = $val['token'];
                $deviceReArr['uuid'][]  = array($val['token'] => $val['uuid']);
            }

            $pushParam['remarks'] = json_encode($deviceReArr['uuid']); #op hw token json格式记录

            $resArr[] = $this->labelsPush($fTag, $pTokenArr[$fTag][$i], $content, $pushType, $pushParam, $msgAc);
        }
        return $resArr;
    }

    /**
     * 华为 / OPPO 标签（指定平台、全平台）推送处理
     * @param string $title    标题
     * @param string $content  内容
     * @param int    $agentId  平台id 0:全平台 其他:指定平台
     * @param string $fTag     单厂商推送，例如："HW"
     * @param int    $pushType 推送类型：0通知 1透传
     * @param array  $msgAc    操作信息
     * @return
     */
    private function labelsHWOPAreaPush($title, $content, $agentId, $fTag, $pushType, $msgAc)
    {
        $pushParam['push_name'] = '';
        $pushParam['title']     = $title;
        $pushParam['agent_id']  = $agentId;

        // 查询设备表中本平台下的所有用户数量
        $deviceCount = DeviceModel::getHWOPTokenList($fTag == 'HW' ? 1 : 2, $agentId, array('iscnt' => 1));

        if (empty($deviceCount)) {
            $res = PushCF::pushReturn(402, array($fTag));
            return array($res);
        }

        $size = $fTag == 'HW' ? 100 : 1000;
        $page = ceil($deviceCount / $size);

        for ($i = 0; $i < $page; $i++) {
            // 查询出当前分页数据
            $param    = array('limit' => array($i * $size, $size));
            $deviceRe = DeviceModel::getHWOPTokenList($fTag == 'HW' ? 1 : 2, $agentId, $param);

            foreach ($deviceRe as $val) {
                $pTokenArr[$fTag][$i][] = $val['token'];
            }

            $pushParam['remarks'] = json_encode(array($agentId ? $agentId : 'ALLPUSH' => $pTokenArr[$fTag][$i])); #op hw token json格式记录

            $resArr[] = $this->labelsPush($fTag, $pTokenArr[$fTag][$i], $content, $pushType, $pushParam, $msgAc);
        }
        return $resArr;
    }

    /**
     * 动作参数处理
     * @param array $msgAc 动作参数
     * @return
     */
    private function getMsgAction($msgAc)
    {
        $msgAcRe['type'] = $msgAc['type'];

        // type1 特别说明: 接收参数参数第二个键同样是data 但是intent协议中参数改为push_code而不是data
        if ($msgAc['type'] === 0 || $msgAc['type'] == 1) {
            if ($this->appOs == 1) {
                $msgAcRe['data']['address'] = $msgAc['data']['androidAdress'];
                if (is_array($msgAc['data']['androidParam']) && !empty($msgAc['data']['androidParam'])) {
                    $msgAcRe['data']['addressParam'] = $msgAc['data']['androidParam'];
                    $this->arrValToString($msgAcRe['data']['addressParam']);
                }
            } elseif ($this->appOs == 2) {
                $msgAcRe['data']['address'] = $msgAc['data']['iosAddress'];
                if (is_array($msgAc['data']['iosParam']) && !empty($msgAc['data']['iosParam'])) {
                    $msgAcRe['data']['addressParam'] = $msgAc['data']['iosParam'];
                    $this->arrValToString($msgAcRe['data']['iosParam']);
                }
            } else {
                return false;
            }
        } elseif ($msgAc['type'] == 2) {
            $msgAcRe['is_sound'] =$msgAc['is_sound'];
        } else {
            return false;
        }

        return $msgAcRe;
    }

    /**
     * 将数组值格式化为字符串
     * @param array $arr
     * @return
     */
    private function arrValToString(&$arr) {
        if (!is_array($arr)) {
            return;
        }

        foreach ($arr as $key => $val) {
            if (!is_array($val)) {
                $arr[$key] = (string)$val;
            } else {
                $this->arrValToString($arr[$key]);
            }
        }
    }

    /**
     * 实例化推送类对象
     * @param string $supplier 厂商
     * @param array  $param    推送配置参数
     * @return object/array
     */
    private function factory($supplier, $param = array())
    {
        switch ($supplier) {
            case 'HW':
                return new HWPush($param);
                break;
            case 'XM':
                return new XMPush($param);
                break;
            case 'MZ':
                return new MZPush($param);
                break;
            case 'OP':
                return new OPPush($param);
                break;
            // case 'VV':
            //     return new VVPush($param);
            //     break;
            // case 'IGT':
            //     return new IGTPush($param);
            //     break;
            case 'JG':
                return new JGPush($param);
                break;
            default:
                return new JGPush($param);
        }
    }
}