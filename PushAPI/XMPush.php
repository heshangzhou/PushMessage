<?php
/**
 * 小米推送API封装类
 * @Filename: XMPush.php 
 * @Author:   wh
 * @Date:     2019-01-22 14:18:39
 * @Description:
 */
class XMPush implements BasePush
{
    private $mastersecret      = '';      // 服务端API鉴权码
    private $packageName       = '';      // 服务端APP包名
    private $offlineExpireTime = 7200000; // 默认离线缓存时间2小时（毫秒级）

    public function __construct($param = array())
    {
        $this->mastersecret = PUSH_XM_APPSECRET;
        $this->packageName  = PUSH_PACKAGE;

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
        return $this->pushByAlias($title, $content, $alias, 0, $param);
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
        return $this->pushByAlias($title, $content, $aliasArr, 0, $param);
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
        if (!is_array($tagArr)) return PushCF::pushReturn(500001);

        if (count($tagArr) == 1) {
            return $this->pushByTopic($title, $content, $tagArr[0], 0, $param);
        } else {
            return $this->pushBymultiTopic($title, $content, $tagArr, 0, $param);
        }
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
        return $this->pushByAlias($content, $content, $alias, 1, $param);
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
        return $this->pushByAlias($content, $content, $aliasArr, 1, $param);
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
        if (!is_array($tagArr)) return PushCF::pushReturn(500001);

        if (count($tagArr) == 1) {
            return $this->pushByTopic('', $content, $tagArr[0], 1, $param);
        } else {
            return $this->pushBymultiTopic('', $content, $tagArr, 1, $param);
        }
    }

    /**
     * 小米别名通用推送
     * @param string $title   标题
     * @param string $content 内容
     * @param string $alias   别名
     * @param int    $through 0通知 1透传
     * @param array  $param   参数数组
     * @return
     */
    public function pushByAlias($title, $content, $alias, $through = 0, $param = array())
    {
        if (!defined('HUI_PUSH_XM_APPSECRET') || !defined('HUI_PUSH_PACKAGE')) {
            return PushCF::pushReturn(500004);
        }

        if (empty($title) || empty($content) || empty($alias)) {
            return PushCF::pushReturn(500001);
        }

        if (is_array($alias)) {
            $aliasSum = count($alias);
            if ($aliasSum > 1000) {
                return PushCF::pushReturn(500002);
            }
            $alias = implode(',', $alias);
        }

        extract($this->getActionType($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.xmpush.xiaomi.com/v3/message/alias';
        // 请求参数
        $urlParams = array(
            'alias'                   => $alias,
            'payload'                 => urlencode($content),
            'restricted_package_name' => $this->packageName,
            'pass_through'            => empty($through) ? 0 : 1,
            'description'             => $content,
            'notify_type'             => 'DEFAULT_SOUND  = 1',
            'time_to_live'            => $this->offlineExpireTime, // 毫秒
            'notify_id'               => md5(uniqid(microtime(true), true)),
            'extra.notify_effect'     => $actionType,
            'extra.intent_uri'        => $intent_uri,
            // 'extra.web_uri'           => $web_uri,
            // 'appPkgName'              => $appPkgName,
        );

        if (empty($through)) {
            $urlParams['title'] = $title;
        }

        // 请求头部信息
        $header = array('Authorization: key=' . $this->mastersecret);
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(500005, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 小米multi标签推送,多个topic推送单条消息
     * @param string       $title   标题
     * @param string       $content 内容
     * @param string/array $topics  标签
     * @param int          $through 0通知 1透传
     * @param array        $param   参数数组
     * @return
     */
    public function pushBymultiTopic($title, $content, $topics, $through = 0, $param = array())
    {
        if (!defined('HUI_PUSH_XM_APPSECRET') || !defined('HUI_PUSH_PACKAGE')) {
            return PushCF::pushReturn(500004);
        }

        if (empty($title) || empty($content) || empty($topics)) {
            return PushCF::pushReturn(500001);
        }

        if (is_array($topics)) {
            $topicsSum = count($topics);
            if ($topicsSum > 5) {
                return PushCF::pushReturn(500003);
            }
            $topics = implode(';$;', $topics);
        }

        extract($this->getActionType($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.xmpush.xiaomi.com/v3/message/multi_topic';
        // 请求参数
        $urlParams = array(
            'topic'                   => $topics,
            'topic_op'                => 'UNION', # UNION 并集, INTERSECTION 交集, EXCEPT 差集
            'payload'                 => urlencode($content),
            'restricted_package_name' => $this->packageName,
            'pass_through'            => empty($through) ? 0 : 1,
            'description'             => $content,
            'notify_type'             => 'DEFAULT_SOUND  = 1',
            'time_to_live'            => $this->offlineExpireTime, // 毫秒
            'notify_id'               => md5(uniqid(microtime(true), true)),
            'extra.notify_effect'     => $actionType,
            'extra.intent_uri'        => $intent_uri,
            // 'extra.web_uri'           => $web_uri,
            // 'appPkgName'              => $appPkgName,
        );

        if (empty($through)) {
            $urlParams['title'] = $title;
        }
        // 请求头部信息
        $header = array('Authorization: key=' . $this->mastersecret);
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(500006, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 小米单标签推送,向某个topic推送某条消息
     * @param string $title   标题
     * @param string $content 内容
     * @param string $topic   单标签
     * @param int    $through through 0通知 1透传
     * @param array  $param   参数数组
     * @return
     */
    public function pushByTopic($title, $content, $topic, $through = 0, $param = array())
    {
        if (!defined('HUI_PUSH_XM_APPSECRET') || !defined('HUI_PUSH_PACKAGE')) {
            return PushCF::pushReturn(500004);
        }

        if (empty($title) || empty($content) || empty($topic)) {
            return PushCF::pushReturn(500001);
        }

        extract($this->getActionType($param));

        // 创建curl对象
        $curl = new BaseHttp();
        // 请求URL
        $url = 'https://api.xmpush.xiaomi.com/v3/message/topic';
        // 请求参数
        $urlParams = array(
            'topic'                   => $topic,
            'payload'                 => urlencode($content),
            'restricted_package_name' => $this->packageName,
            'pass_through'            => empty($through) ? 0 : 1,
            'description'             => $content,
            'notify_type'             => 'DEFAULT_SOUND  = 1',
            'time_to_live'            => $this->offlineExpireTime, // 毫秒
            'notify_id'               => md5(uniqid(microtime(true), true)),
            'extra.notify_effect'     => $actionType,
            'extra.intent_uri'        => $intent_uri,
            // 'extra.web_uri'           => $web_uri,
            // 'appPkgName'              => $appPkgName,
        );

        if (empty($through)) {
            $urlParams['title'] = $title;
        }
        // 请求头部信息
        $header = array('Authorization: key=' . $this->mastersecret);
        // 发送请求
        $res = $curl->http_post($url, $urlParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(500007, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
    * 全平台推送 全部用户
    * @param string $title   标题
    * @param string $content 内容
    * @param int    $through 0通知 1透传
    * @param array  $param   参数数组
    * @return
    */
    public function pushByAll($title, $content, $through = 0, $param = array())
    {
       if (empty($title) || empty($content)) {
           return PushCF::pushReturn(500001);
       }

       extract($this->getActionType($param));

       // 创建curl对象
       $curl = new BaseHttp();
       // 请求URL
       $url = 'https://api.xmpush.xiaomi.com/v3/message/all';
       // 请求参数
       $urlParams = array(
           'payload'                 => urlencode($content),
           'restricted_package_name' => $this->packageName,
           'pass_through'            => empty($through) ? 0 : 1,
           'description'             => $content,
           'notify_type'             => 'DEFAULT_SOUND  = 1',
           'time_to_live'            => $this->offlineExpireTime, // 毫秒
           'notify_id'               => md5(uniqid(microtime(true), true)),
           'extra.notify_effect'     => $actionType,
           'extra.intent_uri'        => $intent_uri,
           'extra.web_uri'           => $web_uri,
           'appPkgName'              => $appPkgName,
       );

       if (empty($through)) {
           $urlParams['title'] = $title;
       }

       // 请求头部信息
       $header = array('Authorization: key=' . $this->mastersecret);
       // 发送请求
       $res = $curl->http_post($url, $urlParams, $header);

       if ($res == 'RequestFailure') {
           return PushCF::pushReturn(500008, $res);
       } else {
           return PushCF::pushReturn(200, $res);
       }
    }

    /*
     * 推送后续动作
     */
    private function getActionType($param)
    {
        // 打开自定义APP页面
        $actionType = 2;
        $intent_uri = "intent:#Intent;component=com.yiweiyun.lifes.huilife/com.yiweiyun.lifes.huilife.override.push.MultipleFunctionActivity;S.data=" . json_encode($param) . ";end";

        // if (array_keys($param)[0] == 'intent') {
        //    // 打开自定义APP页面
        //    $actionType = 2;
        //    $intent_uri = $param['intent'];
        // } elseif (array_keys($param)[0] == 'url') {
        //    // 打开指定url
        //    $actionType = 3;
        //    $web_uri    = $param['url'];
        // } else {
        //    // 打开APP首页
        //    $actionType = 1;
        //    $appPkgName = $this->packageName;
        // }

        $actionType = array(
            'actionType' => $actionType ? $actionType : 1,
            'intent_uri' => $intent_uri,
            // 'web_uri'    => $web_uri,
            // 'appPkgName' => $appPkgName,
        );
        return $actionType;
    }
}