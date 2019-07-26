<?php
/**
 * 华为推送API封装类
 * @Filename: HWPush.php 
 * @Author:   wh
 * @Date:     2019-01-22 14:26:46
 * @Description:
 */
class HWPush implements BasePush
{
    private $appid             = '';
    private $appsecret         = '';
    private $offlineExpireTime = 7200; // 离线缓存时间（秒值）

    public function __construct($param = array())
    {
        $this->appid     = PUSH_HW_APPID;
        $this->appsecret = PUSH_HW_APPSECRET;

        $param['offlineExpireTime'] && $this->offlineExpireTime = $param['offlineExpireTime'];
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
        $aliasArr[] = $alias;
        return $this->commonPush($title, $content, $aliasArr, $param);
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
        $aliasArr[] = $alias;
        return $this->commonTransPush($content, $aliasArr, $param);
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
        return $this->commonTransPush($content, $aliasArr, $param);
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
     * 华为推送通用通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function commonPush($title, $content, $aliasArr, $param = array())
    {
        if (!defined('HUI_PUSH_HW_APPID') || !defined('HUI_PUSH_HW_APPSECRET')) {
            return PushCF::pushReturn(200002);
        }

        if (empty($title) || empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushCF::pushReturn(200001);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 100) {
            return PushCF::pushReturn(200003);
        }

        $token = $this->getAccessToken();
        if (empty($token)) {
            return PushCF::pushReturn(200004);
        }

        $actionType = 1;
        $param      = array('intent' => "intent://app#Intent;scheme=launcher;package=com.yiweiyun.lifes.huilife;S.data=" . json_encode($param) . ";end");

//        if (array_keys($param)[0] == 'intent') {
//            // 打开自定义APP页面
//            $actionType = 1;
//            $param      = array('intent' => $param['intent']);
//        } elseif (array_keys($param)[0] == 'url') {
//            // 打开指定url
//            $actionType = 2;
//            $param      = array('url' => $param['url']);
//        } else {
//            // 打开APP首页
//            $actionType = 3;
//            $param      = array('appPkgName' => HUI_PUSH_PACKAGE);
//        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.push.hicloud.com/pushsend.do?nsp_ctx=' . $this->getNspCtx();
        // 请求参数
        $payload = array(
            "hps" => array(
                "msg" => array(
                    "type"   => 3, // 1：透传异步消息  3：系统通知栏异步消息
                    "body"   => array(
                        "content" => $content,
                        "title"   => $title,
                    ),
                    "action" => array(
                        "type"  => $actionType,
                        "param" => $param,
                    ),
                ),
            ),
        );

        $params = 'access_token=' . $token;
        $params .= '&nsp_svc=openpush.message.api.send';
        $params .= '&nsp_ts=' . time();
        $params .= '&device_token_list=' . urlencode(json_encode($aliasArr)); // 单次推送最多100
        $params .= '&payload=' . urlencode(json_encode($payload));
        $params .= '&expire_time=' . date("Y-m-d\TH:m", time() + 3600);

        // 请求头部信息
        $header = array('Content-Type: application/x-www-form-urlencoded');
        // 发送请求
        $res = $curl->http_post($url, $params, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(200005, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 华为推送通用透传
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    public function commonTransPush($content, $aliasArr, $param = array())
    {
        if (!defined('HUI_PUSH_HW_APPID') || !defined('HUI_PUSH_HW_APPSECRET')) {
            return PushCF::pushReturn(200002);
        }

        if (empty($content) || empty($aliasArr) || !is_array($aliasArr)) {
            return PushCF::pushReturn(200001);
        }

        $aliasSum = count($aliasArr);
        if ($aliasSum > 100) {
            return PushCF::pushReturn(200003);
        }

        $token = $this->getAccessToken();
        if (empty($token)) {
            return PushCF::pushReturn(200004);
        }

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.push.hicloud.com/pushsend.do?nsp_ctx=' . $this->getNspCtx();
        // 请求参数
        $payload = array(
            "hps" => array(
                "msg" => array(
                    "type" => 1, // 1：透传异步消息  3：系统通知栏异步消息
                    "body" => array(
                        "content" => array(
                            'key' => $content
                        )
                    )
                )
            )
        );
        $params = 'access_token=' . $token;
        $params .= '&nsp_svc=openpush.message.api.send';
        $params .= '&nsp_ts=' . time();
        $params .= '&device_token_list=' . urlencode(json_encode($aliasArr)); // 单次推送最多100
        $params .= '&payload=' . urlencode(json_encode($payload));
        $params .= '&expire_time=' . date("Y-m-d\TH:m", time() + 3600);
        // 请求头部信息
        $header = array('Content-Type: application/x-www-form-urlencoded');
        // 发送请求
        $res = $curl->http_post($url, $params, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(200006, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 请求鉴权 获取权限令牌
     * @return
     */
    private function getAccessToken()
    {
        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://login.cloud.huawei.com/oauth2/v2/token';
        // 请求参数
        $params = 'grant_type=client_credentials&client_secret=' . urlencode($this->appsecret) . '&client_id=' . urlencode($this->appid);
        // 请求头部信息
        $header = array('Content-Type: application/x-www-form-urlencoded');
        // 发送请求
        $result = $curl->http_post($url, $params, $header);
        // var_dump($result);

        return urlencode(json_decode($result)->access_token);
    }

    /**
     * 获取请求参数nsp_ctx
     * @return
     */
    private function getNspCtx()
    {
        $nspCtx = array(
            'ver'   => 1,
            'appId' => $this->appid,
        );

        return urlencode(json_encode($nspCtx));
    }
}