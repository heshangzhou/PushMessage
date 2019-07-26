<?php
/**
 * 魅族推送API封装类
 * @Filename: MZPush.php 
 * @Author:   wh
 * @Date:     2019-01-22 14:20:58
 * @Description:
 */
class MZPush implements BasePush
{
    private $appid        = '';
    private $mastersecret = ''; // 服务端API鉴权码

    public function __construct($param = array())
    {
        $this->appid        = PUSH_MZ_APPID;
        $this->mastersecret = PUSH_MZ_APPSECRET;
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
        return $this->pushByAlias($title, $content, $alias, $param);
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
        return $this->pushByAlias($title, $content, $aliasArr, $param);
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
        return $this->PushByTag($title, $content, $tagArr, $param);
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
        return PushCF::pushReturn(400006);
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
        return PushCF::pushReturn(400006);
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
        return PushCF::pushReturn(400006);
    }

    /**
     * 魅族推送 别名通用通知
     * @param string       $title   标题
     * @param string       $content 内容
     * @param string/array $alias   别名字符串
     * @param array        $param   参数数组
     * @return
     */
    public function pushByAlias($title, $content, $alias, $param = array())
    {
        if (!defined('HUI_PUSH_MZ_APPID') || !defined('HUI_PUSH_MZ_APPSECRET')) {
            return PushCF::pushReturn(400002);
        }

        if (empty($title) || empty($content) || empty($alias)) {
            return PushCF::pushReturn(400001);
        }

        $aliasStr = "";
        if (is_array($alias)) {
            $aliasSum = count($alias);
            if ($aliasSum > 1000) {
                return PushCF::pushReturn(400003);
            }

            foreach ($alias as $val) {
                $aliasStr .= $val . ",";
            }
            $aliasStr = substr($aliasStr, 0, -1);
        } else {
            $aliasStr = $alias;
        }

        $msgJson = $this->PushMsg($title, $content, $param);
        $signArr = array(
            'appId'       => $this->appid,
            'alias'       => $aliasStr,
            'messageJson' => $msgJson,
        );

        $mzSign = $this->PushSign($signArr);

        $mzParams = "appId=" . urlencode($this->appid);
        $mzParams .= "&alias=" . urlencode($aliasStr);
        $mzParams .= "&messageJson=" . urlencode($msgJson);
        $mzParams .= "&sign=" . urlencode($mzSign);

        $curl   = new BaseHttp();
        $url    = 'http://api-push.meizu.com/garcia/api/server/push/varnished/pushByAlias';
        $header = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        );
        $res = $curl->http_post($url, $mzParams, $header);

        if ($res == 'RequestFailure') {
            return PushCF::pushReturn(400004, $res);
        } else {
            return PushCF::pushReturn(200, $res);
        }
    }

    /**
     * 魅族推送标签通用通知
     * @param string $title   标题
     * @param string $content 内容
     * @param array  $tagArr  标签数组
     * @param array  $param   参数数组
     * @return
     */
    public function PushByTag($title, $content, $tagArr, $param = array())
    {
        if (!defined('HUI_PUSH_MZ_APPID') || !defined('HUI_PUSH_MZ_APPSECRET')) {
            return PushCF::pushReturn(400002);
        }

        if (empty($title) || empty($content) || empty($tagArr) || !is_array($tagArr)) {
            return PushCF::pushReturn(400001);
        }

        $msgJson = $this->PushMsg($title, $content, $param);
        $tagStr  = implode(',', $tagArr);

        $signArr = array(
            'appId'       => $this->appid,
            'pushType'    => 0,
            'tagNames'    => $tagStr,
            'scope'       => 0,
            'messageJson' => $msgJson,
        );
        $mzSign = $this->PushSign($signArr);

        $mzParams = "appId=" . urlencode($this->appid);
        $mzParams .= "&pushType=0";
        $mzParams .= "&tagNames=" . urlencode($tagStr);
        $mzParams .= "&scope=0";
        $mzParams .= "&messageJson=" . urlencode($msgJson);
        $mzParams .= "&sign=" . urlencode($mzSign);

        $curl   = new BaseHttp();
        $url    = 'http://api-push.meizu.com/garcia/api/server/push/pushTask/pushToTag';
        $header = array(
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        );
        $result = $curl->http_post($url, $mzParams, $header);

        if ($result == 'RequestFailure') {
            return PushCF::pushReturn(400005, $result);
        } else {
            return PushCF::pushReturn(200, $result);
        }
    }

    /**
     * 魅族验证签名生成
     * @param array
     * @return
     */
    private function PushSign($arr = array())
    {
        ksort($arr);
        $data = '';
        foreach ($arr as $key => $value) {
            $data .= $key . "=" . $value;
        }
        $data = $data . $this->mastersecret;
        $sign = md5($data, false);
        return $sign;
    }

    /**
     * 公用的推送消息
     * @param string $title   标题
     * @param string $content 内容
     * @param array  $param
     * @return
     */
    private function PushMsg($title, $content, $param = array())
    {
        // 请求参数
        // 打开自定义APP内页
        $actionType = 2;
        $actionVal  = "launcher://app?data=" . json_encode($param);

//        if (array_keys($param)[0] == 'intent') {
//            // 打开自定义APP页面
//            $actionType = 1;
//            $actionVal  = $param['intent'];
//        } elseif (array_keys($param)[0] == 'url') {
//            // 打开指定url
//            $actionType = 2;
//            $actionVal  = $param['url'];
//        } else {
//            // 打开APP首页
//            $actionType = 0;
//            $actionVal  = '';
//        }

        $msg = array(
            'noticeBarInfo'    => array(
                'noticeBarType' => 0, //通知栏样式(0, 标准) int 非必填，值为0
                'title'         => $title, //推送标题,  string 必填，字数限制1~32
                'content'       => $content, //推送内容,  string 必填，字数限制1~100
            ),
            'noticeExpandInfo' => array(
                'noticeExpandType' => 0, //展开方式 (0, 标准),(1, 文本) int 非必填，值为0、1
                // 'noticeExpandContent' => '', //展开内容,  string noticeExpandType为文本时，必填
            ),
            'clickTypeInfo'    => array(
                'clickType' => $actionType, //点击动作 (0,打开应用),(1,打开应用页面),(2,打开URI页面),(3, 应用客户端自定义) int 非必填,默认为0
                'url'       => $actionVal, //URI页面地址,  string clickType为打开URI页面时，必填, 长度限制1000字节
                // 'parameters' => '', //参数  JSON格式 非必填
                // 'activity'  => $actionVal, //应用页面地址  string clickType为打开应用页面时，必填, 长度限制1000字节
                // 'customAttribute' => '', //应用客户端自定义 string clickType为应用客户端自定义时，必填， 输入长度为1000字节以内
            ),
            'pushTimeInfo'     => array(
                'offLine' => 0, //是否进离线消息(0 否 1 是[validTime])  int 非必填，默认值为1
                // 'validTime' => 12, //有效时长 (1 72 小时内的正整数)  int offLine值为1时，必填，默认24
            ),
            'advanceInfo'      => array(
                // 'suspend' => 1,    //是否通知栏悬浮窗显示 (1 显示  0 不显示)  int 非必填，默认1
                // 'clearNoticeBar' => '', //是否可清除通知栏 (1 可以  0 不可以)  int 非必填，默认1
                // 'isFixDisplay' => '', //是否定时展示 (1 是  0 否)  int 非必填，默认0
                // 'fixStartDisplayTime' => '', //定时展示开始时间(yyyy-MM-dd HH:mm:ss)  str 非必填
                // 'fixEndDisplayTime' => '', //定时展示结束时间(yyyy-MM-dd HH:mm:ss)  str 非必填
                'notificationType' => array(
                    'vibrate' => 1, //震动 (0关闭  1 开启) ,   int 非必填，默认1
                    // 'lights' => '', //闪光 (0关闭  1 开启),  int 非必填，默认1
                    // 'sound' => '', //声音 (0关闭  1 开启),  int 非必填，默认1
                ),
            ),
        );
        $msgJson = json_encode($msg);
        return $msgJson;
    }
}