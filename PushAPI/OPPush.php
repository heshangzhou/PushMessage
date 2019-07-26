<?php
/**
 * OPPO推送API封装类
 * @Filename: OPPush.php 
 * @Author:   wh
 * @Date:     2019-01-22 14:23:18
 * @Description:
 */
class OPPush implements BasePush
{
    private $appkey       = '';
    private $mastersecret = ''; // 服务端API鉴权码

    public function __construct($param = array())
    {
        $this->appkey       = PUSH_OP_APPKEY;
        $this->mastersecret = PUSH_OP_MASTERSECRET;
    }

    /**
     * 单用户通知
     * @param string $title   标题
     * @param string $content 内容
     * @param string $alias   别名
     * @param array  $param   参数数组
     * @return
     */
    public function singleUserPush($title, $content, $alias, $param = array())
    {
        if (!defined('HUI_PUSH_OP_MASTERSECRET')) {
            return PushCF::pushReturn(300004);
        }

        if (empty($title) || empty($content) || empty($alias)) {
            return PushCF::pushReturn(300001);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(300005);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.push.oppomobile.com/server/v1/message/notification/unicast';

        extract($this->getActionType($param));

        $data = array(
            'target_type'  => 2, // 使用registration_id推送 2,别名推送alias_name 3
            'target_value' => $alias, // 推送目标用户
            'notification' => array(
                'title'             => $title,
                'content'           => $content,
                'click_action_type' => $actionType,
                $actionKey          => $actionVal,
            ),
        );
        $urlParams = 'message=' . urlencode(json_encode($data));

        // 请求头部信息
        $header = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            'auth_token: ' . $authtoken,

        );
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(300009, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 多用户通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function multiuserPush($title, $content, $aliasArr, $param = array())
    {
        return $this->commonPush($title, $content, $aliasArr, $param);
    }

    /**
     * 标签通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名
     * @param array  $param    参数数组
     * @return
     */
    public function labelsPush($title, $content, $aliasArr, $param = array())
    {
        return $this->commonPush($title, $content, $aliasArr, $param);
    }

    /**
     * 单用户透传
     * @param string $content 内容
     * @param string $alias   别名
     * @param array  $param   参数数组
     * @return
     */
    public function singleUserTransPush($content, $alias, $param = array())
    {
        return PushCF::pushReturn(300008);
    }

    /**
     * 多用户透传
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function multiuserTransPush($content, $aliasArr, $param = array())
    {
        return PushCF::pushReturn(300008);
    }

    /**
     * 标签透传
     * @param string $content  内容
     * @param array  $aliasArr 别名
     * @param array  $param    参数数组
     * @return
     */
    public function labelsTransPush($content, $aliasArr, $param = array())
    {
        return PushCF::pushReturn(300008);
    }

    /**
     * OPPO推送通用通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名数组 暂时为设备ID数组
     * @param array  $param    参数数组
     * @return
     */
    public function commonPush($title, $content, $aliasArr = array(), $param = array())
    {
        if (!defined('HUI_PUSH_OP_MASTERSECRET')) {
            return PushCF::pushReturn(300004);
        }

        if (empty($title) || empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushCF::pushReturn(300001);
        }
        $aliasSum = count($aliasArr);
        if ($aliasSum > 1000) {
            return PushCF::pushReturn(300002);
        }
        $aliasStr = implode(';', $aliasArr);
        $aliasStr = '&target_value=' . trim($aliasStr, ';');

        // 获取权限令牌
        $authToken = $this->getAuthToken();
        if (empty($authToken)) {
            return PushCF::pushReturn(300005);
        }

        // 创建消息共同体
        $messageId = $this->saveListBody($title, $content, $param);
        if (empty($messageId)) {
            return PushCF::pushReturn(300006);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.push.oppomobile.com/server/v1/message/notification/broadcast';

        // 请求参数
        $urlParams = 'message_id=' . $messageId;
        $urlParams .= '&target_type=2' . $aliasStr; // 目标类型为registration_id

        // 请求头部信息
        $header = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            'auth_token: ' . $authToken,
        );
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(300010, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 保存消息共同体
     * @param string $title  标题
     * @param string $cotent 内容
     * @return
     */
    private function saveListBody($title, $content, $param = array())
    {
        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.push.oppomobile.com/server/v1/message/notification/save_message_content';

        // 请求参数
        extract($this->getActionType($param));

        $urlParams = 'title=' . urlencode($title);
        $urlParams .= '&content=' . urlencode($content);
        $urlParams .= '&click_action_type=' . $actionType;
        if (!empty($actionKey)) {
            $urlParams .= '&' . $actionKey . '=' . $actionVal;
        }

        // 请求头部信息
        $header = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            'auth_token: ' . $this->getAuthToken(),
        );
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        $resObj = json_decode($res);
        return $resObj->data->message_id;
    }

    /**
     * 请求鉴权 获取权限令牌
     * @return
     */
    private function getAuthToken()
    {
        // 创建curl对象
        $curl = new BaseHttp();
        // 生成sha256加密标识
        $sign = hash("sha256", $this->appkey . $this->getMsectime() . $this->mastersecret);
        // 请求URL
        $url = 'https://api.push.oppomobile.com/server/v1/auth';
        // 请求参数
        $params = 'app_key=' . $this->appkey;
        $params .= '&sign=' . $sign;
        $params .= '&timestamp=' . urlencode($this->getMsectime());

        // 请求头部信息
        $header = array('Content-Type: application/x-www-form-urlencoded;charset=utf-8');
        // 发送请求
        $res = $curl->http_post($url, $params, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(300005, $res);
        } else {
            $resObj = json_decode($res);
            if ($resObj->code != 0) {
                return 0;
            }
            return $resObj->data->auth_token;
        }
    }

    /**
     * 获取毫秒级时间戳
     * @return
     */
    private function getMsectime()
    {
        list($msec, $sec) = explode(' ', microtime());

        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

    /*
     * 推送后续动作
     */
    public function getActionType($param)
    {
        // 请求参数
        // 打开自定义APP内页
        $actionType = 5;
        $actionKey  = 'click_action_url';
        $actionVal  = "launcher://app?data=" . json_encode($param);

//        if (array_keys($param)[0] == 'intent') {
//            // 打开自定义APP页面
//            $actionType = 5;
//            $actionKey  = 'click_action_url';
//            $actionVal  = $param['intent'];
//        } elseif (array_keys($param)[0] == 'url') {
//            // 打开指定url
//            $actionType = 2;
//            $actionKey  = 'click_action_url';
//            $actionVal  = $param['url'];
//        } else {
//            // 打开APP首页
//            $actionType = 0;
//            $actionKey  = '';
//            $actionVal  = '';
//        }

        $actionType = array(
            'actionType' => $actionType,
            'actionKey'  => $actionKey,
            'actionVal'  => $actionVal,
        );
        return $actionType;
    }
}