<?php
/**
 * 推送接口
 * @FileName: BasePush.php 
 * @Author:   wh
 * @Date:     2019-01-24 12:31:43
 * @Description:
 */
interface BasePush
{
    /**
     * 单用户通知
     * @param string $title   标题
     * @param string $content 内容
     * @param string $alias   别名
     * @param array  $param   参数数组
     * @return
     */
    function singleUserPush($title, $content, $alias, $param = array());

    /**
     * 多用户通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    function multiuserPush($title, $content, $aliasArr, $param = array());

    /**
     * 标签通知
     * @param string $title    标题
     * @param string $content  内容
     * @param array  $aliasArr 别名
     * @param array  $param    参数数组
     * @return
     */
    function labelsPush($title, $content, $aliasArr, $param = array());

    /**
     * 单用户透传
     * @param string $content 内容
     * @param string $alias   别名
     * @param array  $param   参数数组
     * @return
     */
    function singleUserTransPush($content, $alias, $param = array());

    /**
     * 多用户透传
     * @param string $content  内容
     * @param array  $aliasArr 别名数组
     * @param array  $param    参数数组
     * @return
     */
    function multiuserTransPush($content, $aliasArr, $param = array());

    /**
     * 标签透传
     * @param string $content  内容
     * @param array  $aliasArr 别名
     * @param array  $param    参数数组
     * @return
     */
    function labelsTransPush($content, $aliasArr, $param = array());
}