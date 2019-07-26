<?php
/**
 * 极光推送API封装类
 * @Filename: JGPush.php 
 * @Author:   wh
 * @Date:     2019-02-26 19:08:52
 * @Description:
 */
class JGPush implements BasePush
{
    private $appkey            = '';
    private $mastersecret      = '';    // 服务端API鉴权码
    private $offlineExpireTime = 86400; // 默认离线缓存时间1天（秒级）

    public function __construct($param = array())
    {
        $this->appkey       = PUSH_JG_APPKEY;
        $this->mastersecret = PUSH_JG_MASTERSECRET;
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
        $audience = array('type' => 'alias', 'arr' => array($alias));
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        return $this->commonPush($title, $content, $audience, $platform, $param);
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
        $audience = array('type' => 'alias', 'arr' => $aliasArr);
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        return $this->commonPush($title, $content, $audience, $platform, $param);
    }

    /**
     * 标签通知
     * @param string $title   标题
     * @param string $content 内容
     * @param array  $tagArr  别名
     * @param array  $param   参数数组
     * @return
     */
    public function labelsPush($title, $content, $tagArr, $param = array())
    {
        $audience = array('type' => 'tag', 'arr' => $tagArr);
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        return $this->commonPush($title, $content, $audience, $platform, $param);
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
        $audience = array('type' => 'alias', 'arr' => array($alias));
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        return $this->commonTransPush('title', $content, $audience, $platform, $param);
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
        $audience = array('type' => 'alias', 'arr' => $aliasArr);
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        return $this->commonTransPush('title', $content, $audience, $platform, $param);
    }

    /**
     * 标签透传
     * @param string $content 内容
     * @param array  $tagArr  别名
     * @param array  $param   参数数组
     * @return
     */
    public function labelsTransPush($content, $tagArr, $param = array())
    {
        $platform = !empty($param['platform']) ? $param['platform'] : 'android';
        $audience = array('type' => 'tag', 'arr' => $tagArr);
        return $this->commonTransPush('title', $content, $audience, $platform, $param);
    }

    /**
     * 极光推送通用通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $audience 推送目标数组 ['type'=>'','arr'=array()]
     * @param string $platform 推送系统
     * @param array  $param    参数数组
     * @return
     */
    public function commonPush($title, $content, $audience, $platform, $param = array())
    {
        // 数据验证
        $validateCode = $this->validateParam($title, $content, $audience, $platform, 1);
        if ($validateCode) {
            return PushCF::pushReturn($validateCode);
        }
        // 通知内容
        $notification = $this->pushBody($title, $content, $platform, $param);

        // True 表示推送生产环境，False 表示要推送开发环境；
        $apnsProduction = !empty($param['apns']) ? true : false;

        $options = array(
            'time_to_live'    => $this->offlineExpireTime,
            'apns_production' => $apnsProduction,
        );

        $base64Auth = base64_encode("$this->appkey:$this->mastersecret");

        // 推送唯一标识符
        // $cid = md5(uniqid(microtime(true), true));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://bjapi.push.jiguang.cn/v3/push';

        // 请求参数
        $urlParams = array(
            'platform'     => $platform,
            'audience'     => array($audience['type'] => $audience['arr']),
            'notification' => $notification,
            'options'      => $options,
            // 'cid' => !empty($param['cid']) ? $param['cid'] : $cid,
        );

        // 请求头部信息
        $header = array('Authorization:Basic ' . $base64Auth);

        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(700009, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 极光推送通用透传
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $audience 推送目标数组 ['type'=>'','arr'=array()]
     * @param string $platform 推送系统
     * @param array  $param    参数数组
     * @return
     */
    public function commonTransPush($title, $content, $audience, $platform, $param = array())
    {
        // 数据验证
        $validateCode = $this->validateParam($title, $content, $audience, $platform, 2);
        if ($validateCode) {
            return PushCF::pushReturn($validateCode);
        }

        $message = $this->pushBodyTrans($title, $content);

        // True 表示推送生产环境，False 表示要推送开发环境；
        $apnsProduction = !empty($param['apns']) ? true : false;

        $options = array(
            'time_to_live'    => $this->offlineExpireTime,
            'apns_production' => $apnsProduction,
        );

        $base64Auth = base64_encode("$this->appkey:$this->mastersecret");

        // 推送唯一标识符
        // $cid = md5(uniqid(microtime(true), true));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://bjapi.push.jiguang.cn/v3/push';

        // 请求参数
        $urlParams = array(
            'platform' => $platform,
            'audience' => array($audience['type'] => $audience['arr']),
            'message'  => $message,
            'options'  => $options,
            // 'cid'      => !empty($param['cid']) ? $param['cid'] : $cid,
        );

        // 请求头部信息
        $header = array('Authorization:Basic ' . $base64Auth);

        $res = $curl->http_post($url, json_encode($urlParams), $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(700010, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 极光推送 参数验证
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $audience 推送目标数组 ['type'=>'','arr'=array()]
     * @param string $platform 推送系统
     * @param int    $pType    推送类型 1通知 2透传
     * @return
     */
    private function validateParam($title, $content, $audience, $platform, $pType)
    {
        if (!defined('HUI_PUSH_JG_APPKEY') || !defined('HUI_PUSH_JG_MASTERSECRET')) {
            return PushCF::pushReturn(700002);
        }

        if (empty($title)       || empty($content)
            || empty($audience) || empty($platform)
            || empty($pType)
        ) {
            return PushCF::pushReturn(700001);
        }

        // 推送目标类型
        $audienceType = array(
            'tag',
            'tag_and',
            'tag_not',
            'alias',
            'registration_id',
            'segment',
            'abtest',
            'all'
        );
        if (empty($audience['type'])   || !in_array($audience['type'], $audienceType)
            || empty($audience['arr']) || !is_array($audience['arr'])
        ) {
            return PushCF::pushReturn(700003);
        }

        $audienceSum = count($audience['arr']);
        if (!in_array($audience['type'], array('tag', 'tag_and', 'tag_not'))
            && $audienceSum > 20) {
            return PushCF::pushReturn(700004);
        } elseif (!in_array($audience['type'], array('alias', 'registration_id'))
            && $audienceSum > 1000) {
            return PushCF::pushReturn(700005);
        } elseif (!in_array($audience['type'], array('segment', 'abtest'))
            && $audienceSum > 1) {
            return PushCF::pushReturn(700006);
        }

        $mSystem = array('android', 'ios', 'all');
        if (empty($platform) || !in_array($platform, $mSystem)) {
            return PushCF::pushReturn(700007);
        }

        if (!in_array($pType, array(1, 2))) {
            return PushCF::pushReturn(700008);
        }
    }

    /**
     * 通知内容体
     * @param string $title    标题
     * @param string $cotent   内容
     * @param string $platform 系统
     * @param string $param    动作参数
     * @return
     */
    private function pushBody($title, $content, $platform, $param = array())
    {
        if ($platform == 'android') {
            $notification['android'] = array(
                'alert'      => $content,
                'title'      => $title,
                'alert_type' => 1, // -1 ~ 7
                'priority'   => 0, // -2 ~ 2
                'intent'     => array(
                    'url' => "intent://app#Intent;scheme=launcher;package=com.yiweiyun.lifes.huilife;S.data=" . json_encode($param) . ";end",
                )
            );
        } elseif ($platform == 'ios') {
             $notification['ios'] = array(
                 'alert'  => array(
                     'title' => $title,
                     'body'  => $content
                 ),
//                 'sound'  => "sound.caf",
//                 'badge'  => 1,
                 'extras' => $param,
             );
        } elseif ($platform == 'winphone') {
            // $notification['winphone'] = array(
            //     'title'      => $content,
            //     '_open_page' => "/friends.xaml",
            //     'extras'     => array("news_id" => 134, "my_key" => "a value"),
            // );
        } else {
            $notification = array();
        }
        return $notification;
    }

    /**
     * 透传内容体 或者称作：自定义消息
     * @param string $title  标题
     * @param string $cotent 内容
     * @return
     */
    private function pushBodyTrans($title, $content)
    {
        $message = array(
            'msg_content'  => $content,
            'content_type' => 'text',
            'title'        => $title,
        );
        return $message;
    }

}