# PushMessage
一、功能简介

手机消息推送，集成华为、小米、OPPO、魅族等厂商的消息推送API，同时集成极光推送平台的消息推送API

二、文件说明

Push.php: 推送测试脚本

PushMsg.php: 消息推送工厂类

PushReport.php: 上报接口（记录用户登录设备信息及token）

PushLink.php: 接收push_code 返回给APP端跳转APP内联页所需参数

HWPush.php: 华为消息推送封装类（调用华为官方API）

MZPush.php: 魅族消息推送封装类（调用魅族官方API）

OPPush.php: OPPO消息推送封装类（调用OPPO官方API）

XMPush.php: 小米消息推送封装类（调用小米官方API）

JGPush.php: 极光消息推送封装类（调用第三方推送平台API）

IGTPush.php: 个推消息推送封装类（调用第三方推送平台API）

BaseHttp.php: curl请求类

BasePush.php: 推送标准接口类

PushCF.php: Error类

PushSetting.php: 推送配置

Device.php: Model类
