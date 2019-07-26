<?php
/**
 * 个推推送API封装类
 * @Filename: IGTPush.php 
 * @Author:   wh
 * @Date:     2019-01-22 14:19:29
 * @Description:
 */
class IGTPush implements BasePush
{
    private $appid             = '';
    private $appkey            = '';
    private $mastersecret      = '';      // 服务端API鉴权码
    private $isOffline         = true;    // 默认开启离线发送
    private $offlineExpireTime = 7200000; // 默认离线缓存时间2小时（毫秒级）

    public function __construct($param = array())
    {
        $this->appid        = PUSH_IGT_APPID;
        $this->appkey       = PUSH_IGT_APPKEY;
        $this->mastersecret = PUSH_IGT_MASTERSECRET;

        $param['isOffline'] && $this->isOffline = $param['isOffline'] == 1 ? true : false;
        $param['offlineExpireTime'] && $this->offlineExpireTime = $param['offlineExpireTime'] * 1000;
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
        if (empty($title) || empty($content) || empty($alias)) {
            return PushCF::pushReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }
        extract($this->getAction($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_single';
        // 请求参数
        $urlParams = array(
            'message'   => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => $msgtype,
            ),
            $msgtype    => array(
                'style'     => array(
                    'type'  => 0,
                    'text'  => $content, // 通知内容
                    'title' => $title, // 通知标题
                ),
                $actionType => $actionTypeVal,
            ),
            'alias'     => $alias,
            'requestid' => md5(uniqid(microtime(true), true)),
        );

        // 请求头部信息
        $header = array('Content-type: application/json', 'authtoken:' . $authtoken);

        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(100006, $res);
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
        if (empty($title) || empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushCF::pushReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 1000) {
            return PushCF::pushReturn(100003);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }

        // 创建消息共同体
        $taskid = $this->saveListBody($title, $content, $param);
        if (empty($taskid)) {
            return PushCF::pushReturn(100005);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_list';
        // 请求参数
        $urlParams = array('alias' => $aliasArr, 'taskid' => $taskid, "need_detail" => true);
        // 请求头部信息
        $header = array(
            'Content-type: application/json',
            'authtoken:' . $authtoken,
        );
        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'Rearray(uestFailure') {
            return PushReturn::curlReturn($res, 100007, 1);
        } else {
            return PushReturn::curlReturn($res, 200, 1);
        }
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
        return $this->commonPush($title, $content, $$aliasArr, $param);
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
        if (empty($content) || empty($alias)) {
            return PushReturn::pushMsgReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_single';
        // 请求参数
        $urlParams = array(
            'message'      => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => "transmission",
            ),
            'transmission' => array(
                'transmission_type'    => true,
                'transmission_content' => $content,
            ),
            'alias'        => $alias,
            'requestid'    => md5(uniqid(microtime(true), true)),
        );
        // 请求头部信息
        $header = array(
            'Content-type: application/json',
            'authtoken:' . $authtoken,
        );
        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushReturn::curlReturn($res, 100009, 1);
        } else {
            return PushReturn::curlReturn($res, 200, 1);
        }
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
        if (empty($content) || empty($alias) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushReturn::pushMsgReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 1000) {
            return PushCF::pushReturn(100003);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }

        // 创建消息共同体
        $taskid = $this->saveListBodyTrans($content);
        if (empty($taskid)) {
            return PushCF::pushReturn(100005);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_list';
        // 请求参数
        $urlParams = array('alias' => $aliasArr, 'taskid' => $taskid, "need_detail" => true);
        // 请求头部信息
        $header = array('Content-type: application/json', 'authtoken:' . $authtoken);
        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushReturn::curlReturn($res, 100010, 1);
        } else {
            return PushReturn::curlReturn($res, 200, 1);
        }
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
        return $this->commonTransPush($content, $aliasArr, $param);
    }

    /**
     * 个推推送通用通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function commonPush($title, $content, $aliasArr, $param = array())
    {
        if (empty($title) || empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushCF::pushReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 1000) {
            return PushCF::pushReturn(100003);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }
        extract($this->getAction($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_app';

        // 请求参数
        $urlParams = array(
            'message'   => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => $msgtype,
            ),
            $msgtype    => array(
                'style'     => array(
                    'type'  => 0,
                    'text'  => $content, // 通知内容
                    'title' => $title, // 通知标题
                ),
                $actionType => $actionTypeVal,
            ),
            'condition' => array(
                array('key' => 'tag', 'values' => $aliasArr, 'opt_type' => 0), // opt_type: 0并, 1交, 2非
            ),
            'requestid' => md5(uniqid(microtime(true), true)),
        );

        // 请求头部信息
        $header = array('Content-type: application/json', 'authtoken:' . $authtoken);

        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(100008, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 个推推送通用透传
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function commonTransPush($content, $aliasArr, $param = array())
    {
        if (empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushReturn::pushMsgReturn(100001);
        }

        if (empty($this->appid) || empty($this->appkey) || empty($this->mastersecret)) {
            return PushCF::pushReturn(100002);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 1000) {
            return PushCF::pushReturn(100003);
        }

        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        if (empty($authtoken)) {
            return PushCF::pushReturn(100004);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/push_app';

        // 请求参数
        $urlParams = array(
            'message'      => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => "transmission",
            ),
            'transmission' => array(
                'transmission_type'    => true,
                'transmission_content' => $content,
            ),
            'condition'    => array(
                array('key' => 'tag', 'values' => $aliasArr, 'opt_type' => 0), // opt_type: 0并, 1交, 2非
            ),
            'requestid'    => md5(uniqid(microtime(true), true)),
        );

        // 请求头部信息
        $header = array('Content-type: application/json', 'authtoken:' . $authtoken);
        // 发送请求
        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(100011, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }

    }

    /**
     * 请求鉴权，获取权限令牌
     * @return
     */
    private function getAuthToken()
    {
        // 创建curl对象
        $curl = new BaseHttp();
        // 生成sha256加密标识
        $sign = hash("sha256", $this->appkey . $this->getMsectime() . $this->mastersecret);
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/auth_sign';
        // 请求参数
        $params = array(
            'sign'      => $sign,
            'timestamp' => $this->getMsectime(),
            'appkey'    => $this->appkey,
        );
        // 请求头部信息
        $header = array('Content-type: application/json');
        // 发送请求
        $result = $curl->http_post($url, json_encode($params), $header);

        return json_decode($result)->auth_token;
    }

    /**
     * 保存消息共同体-通知栏推送
     * @param string $title  标题
     * @param string $cotent 内容
     * @param array  $param  通知栏行为参数
     * @return
     */
    private function saveListBody($title, $content, $param = array())
    {
        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        extract($this->getAction($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/save_list_body';
        // 请求参数
        $urlParams = array(
            'message' => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => $msgtype,
            ),
            $msgtype  => array(
                'style'     => array(
                    'type'  => 0,
                    'text'  => $content, // 通知内容
                    'title' => $title, // 通知标题
                ),
                $actionType => $actionTypeVal,
            ),
        );

        // 请求头部信息
        $header = array(
            'Content-type: application/json',
            'authtoken:' . $authtoken,
        );
        // 发送请求
        $result = $curl->http_post($url, json_encode($urlParams), $header);

        return json_decode($result)->taskid;
    }

    /**
     * 保存消息共同体-透传
     * @param string $cotent 内容
     * @return
     */
    private function saveListBodyTrans($content)
    {
        // 获取权限令牌
        $authtoken = $this->getAuthToken();
        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://restapi.getui.com/v1/' . $this->appid . '/save_list_body';
        // 请求参数
        $params = array(
            'message'      => array(
                'appkey'              => $this->appkey,
                'is_offline'          => $this->isOffline,
                'offline_expire_time' => $this->offlineExpireTime, // 毫秒
                'msgtype'             => "transmission",
            ),
            'transmission' => array(
                // 'transmission_type' => true,
                'transmission_content' => $content,
            ),
        );

        // 请求头部信息
        $header = array(
            'Content-type: application/json',
            'authtoken:' . $authtoken,
        );
        // 发送请求
        $result = $curl->http_post($url, json_encode($params), $header);

        return json_decode($result)->taskid;
    }

    /**
     * 获取通知栏点击行为
     * @param string $title  标题
     * @param string $cotent 内容
     * @return
     */
    private function getAction($param)
    {
        // 请求参数
        if (array_keys($param)[0] == 'intent') {
            // 打开自定义APP页面
            $msgtype       = 'startactivity';
            $actionType    = 'intent';
            $actionTypeVal = $param['intent'];
        } elseif (array_keys($param)[0] == 'url') {
            // 打开指定url
            $msgtype       = 'link';
            $actionType    = 'url';
            $actionTypeVal = $param['url'];
        } else {
            // 打开APP首页
            $msgtype       = 'notification';
            $actionType    = 'transmission_type';
            $actionTypeVal = true;
        }

        return array(
            'msgtype'       => $msgtype,
            'actionType'    => $actionType,
            'actionTypeVal' => $actionTypeVal,
        );
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
}