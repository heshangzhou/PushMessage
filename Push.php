<?php
/**
 * 推送测试脚本
 * @Filename: Push.php
 * @Author:   wh
 * @Date:     2019-01-22 18:10:00
 * @Description:
 */

//error_reporting(E_ALL);
//ini_set("display_errors", 1);

require_once('/PushAPI/PushSetting.php');
spl_autoload_register(function ($className) {
    $filename = "/PushAPI/{$className}.php";
    if (file_exists($filename)) {
        require_once($filename);
    }
});

$pModel   = isset($_REQUEST['pmodel']) ? $_REQUEST['pmodel'] : 0; // 测试用的 指定推送的类型
$title    = isset($_REQUEST['title']) ? $_REQUEST['title'] : 'default title'; // 推送标题
$title    .= 'T:' . date('m/d H:i:s', time());
$content  = isset($_REQUEST['content']) ? $_REQUEST['content'] : 'default content'; // 推送内容
$content  .= time();
$userId   = isset($_REQUEST['userid']) ? $_REQUEST['userid'] : 0; // 用户ID
$agentId  = isset($_REQUEST['agentid']) ? $_REQUEST['agentid'] : 0; // 代理商ID
$pushType = isset($_REQUEST['pushtype']) ? $_REQUEST['pushtype'] : 0; // 推送类型 0通知 1透传
$msgAc    = isset($_REQUEST['intent']) ? json_decode($_REQUEST['intent'], true) : array(); // 此处intent格式需要与APP端对接定义 是提供给APP端使用的
$appOs    = (int)$_REQUEST['device_os'] == 2 ? 2 : 1; // 推送目标手机系统 1:Android 2:IOS

$pushObj = new PushMsg($appOs);

if (in_array($pModel, array(6, 7, 8))) {
    return false;
}

if ($pModel == 1) {
    // 单用户推送(通知/透传)
    $res = $pushObj->singleUserPush($title, $content, $userId, $agentId, $pushType, $msgAc);
} elseif ($pModel == 2) {
    // 多用户推送(通知/透传)
    $res = $pushObj->multiuserPush($title, $content, explode(',', $userId), $agentId, $pushType, $msgAc);
//    $res = $pushObj->multiuserPush($title, $content, array(16920633, 16929911, 16928786, 16910430, 16635759, 16751515), 1346191, $pushType, $msgAc);
//    $res = $pushObj->multiuserPush($title, $content, array(17545158), 1346191, $pushType, $msgAc);
} elseif ($pModel == 3) {
    // 区域普通用户推送(通知/透传)f
    $res = $pushObj->userPushByAreaNoVip($title, $content, $agentId, $pushType, $msgAc);
} elseif ($pModel == 4) {
    // 区域VIP用户推送(通知/透传)
    $res = $pushObj->userPushByAreaVip($title, $content, $agentId, $pushType, $msgAc);
} elseif ($pModel == 5) {
    // 区域用户推送(通知/透传)
    $res = $pushObj->userPushByArea($title, $content, $agentId, $pushType, $msgAc);
} elseif ($pModel == 6) {
    // 普通用户推送(通知/透传)
    $res = $pushObj->userPushByNoVip($title, $content, $pushType, $msgAc);
} elseif ($pModel == 7) {
    // VIP用户推送(通知/透传)
    $res = $pushObj->userPushByVip($title, $content, $pushType, $msgAc);
} elseif ($pModel == 8) {
    // 全平台用户推送(通知/透传)
    $res = $pushObj->userPushByAll($title, $content, $pushType, $msgAc);
} else {
    echo 'invalid pModel';
    die;
}

header('content-type: text/html;charset=utf-8');

if (is_array($res)) {
    foreach ($res as $key => $val) {
        if (is_array($val)) {
            foreach ($val as $v) {
                $resArr[$key][] = json_decode($v, true);
            }
        } else {
            $resArr[$key] = json_decode($val, true);
        }
    }
    print_r($resArr);
    exit;
}

print_r(json_decode($res, true));