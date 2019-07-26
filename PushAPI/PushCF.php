<?php
/**
 * 推送消息共用函数封装类
 * @Filename: PushCF.php
 * @Author:   wh
 * @Date:     2019-02-23 15:25:54
 * @Description:
 */
class PushCF
{
    public static function errorMsg($code)
    {
        /**
         * IGT: 1000**
         * HW:  2000**
         * OP:  3000**
         * MZ:  4000**
         * XM:  5000**
         * VV:  6000**
         */
        $errorlist = array(
            200    => '请求成功',
            201    => '成功',
            400    => '设备信息为空',
            401    => 'HW或OPPO设备token不能为空',
            402    => '用户信息错误',
            403    => '用户TOKEN错误',
            404    => '用户别名错误',
            405    => '推送记录生成失败',
            406    => '推送TAG获取失败',
            407    => '缺少必要的参数',
            408    => 'ACTION类型错误',
            100001 => 'IGT: 缺少必要的参数',
            100002 => 'IGT: 配置获取失败',
            100003 => 'IGT: 数量超限 数量不能超过1000个',
            100004 => 'IGT: TOKEN错误',
            100005 => 'IGT: 消息共同体保存失败',
            100006 => 'IGT: 单发通知网络错误',
            100007 => 'IGT: 群发通知网络错误',
            100008 => 'IGT: TAG通知网络错误',
            100009 => 'IGT: 单发透传网络错误',
            100010 => 'IGT: 群发透传网络错误',
            100011 => 'IGT: TAG透传网络错误',
            200001 => 'HW: 缺少必要的参数',
            200002 => 'HW: 配置获取失败',
            200003 => 'HW: 数量超限 数量不能超过100个',
            200004 => 'HW: TOKEN错误',
            200005 => 'HW: 通知网络错误',
            200006 => 'HW: 透传网络错误',
            300001 => 'OP: 缺少必要的参数',
            300002 => 'OP: 数量超限 数量不能超过1000个',
            300003 => 'OP: 该类型不存在',
            300004 => 'OP: 配置获取失败',
            300005 => 'OP: TOKEN错误',
            300006 => 'OP: 消息共同体保存失败',
            300007 => 'OP: 数量超限 数量不能超过1000个',
            300008 => 'OP: 官方不支持透传',
            300009 => 'OP: 单发通知网络错误',
            300010 => 'OP: 群发通知网络错误',
            400001 => 'MZ: 缺少必要的参数',
            400002 => 'MZ: 配置获取失败',
            400003 => 'MZ: 数量超限 数量不能超过1000个',
            400004 => 'MZ: ALIAS通知网络错误',
            400005 => 'MZ: TAG通知网络错误',
            400006 => 'MZ: 官方不支持透传',
            500001 => 'XM: 缺少必要的参数',
            500002 => 'XM: 数量超限 数量不能超过1000个',
            500003 => 'XM: TOPIC数量不能超过5条',
            500004 => 'XM: 配置获取失败',
            500005 => 'XM: ALIAS网络错误',
            500006 => 'XM: MULTI_TOPIC网络错误',
            500007 => 'XM: TOPIC网络错误',
            500008 => 'XM: ALL网络错误',
            600001 => 'VV: 缺少必要的参数',
            600002 => 'VV: 网络错误',
            700001 => 'JG: 缺少必要的参数',
            700002 => 'JG: 配置获取失败',
            700003 => 'JG: 推送目标配置错误',
            700004 => 'JG: 数量超限 数量不能超过20个',
            700005 => 'JG: 数量超限 数量不能超过1000个',
            700006 => 'JG: 数量超限 数量不能超过1个',
            700007 => 'JG: 推送平台错误',
            700008 => 'JG: 推送类型错误',
            700009 => 'JG: 通知网络错误',
            700010 => 'JG: 透传网络错误'
        );

        return $errorlist[$code] ? $errorlist[$code] : '';
    }

    /**
     * 请求推送接口返回数据的处理
     * @param int   $code 状态码
     * @param array $date 返回数据
     * @return
     */
    public static function pushReturn($code, $data = array())
    {
        $dataReturn = array(
            'code' => $code,
            'msg'  => self::errorMsg($code),
            'data' => json_decode($data, true),
        );
        $dataJoin = json_encode($dataReturn);

        return $dataJoin;
    }
}