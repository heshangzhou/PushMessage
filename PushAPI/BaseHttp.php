<?php
/**
 * 推送API HTTP基础封装类
 * @FileName: BaseHttp.php
 * @Author:   wh
 * @Date:     2019-01-22 14:44:56
 * @Description: 
 */
class BaseHttp
{
    /**
     * 发送POST请求
     * @param string     $url    请求地址
     * @param array|json $params 请求参数
     * @param array      $header 请求头信息
     * @return mixed
     */
    public function http_post($url, $params, array $header = array(), $retries = 5, $timeout = 10)
    {
        // 初始化curl
        $curlRequest = curl_init();
        // 设置请求的URL
        curl_setopt($curlRequest, CURLOPT_URL, $url);
        // 不进行证书的检测
        curl_setopt($curlRequest, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlRequest, CURLOPT_SSL_VERIFYHOST, 0);
        // 不返回响应头信息
        curl_setopt($curlRequest, CURLOPT_HEADER, 0);
        // 不直接输出到终端上
        curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, 1);
        // 设置超时时间 单位 秒
        curl_setopt($curlRequest, CURLOPT_TIMEOUT, $timeout);
        // 设置发送的请头信息
        curl_setopt($curlRequest, CURLOPT_HTTPHEADER, $header);
        // 开启POST提交
        curl_setopt($curlRequest, CURLOPT_POST, 1);
        // 提交的数据
        curl_setopt($curlRequest, CURLOPT_POSTFIELDS, $params);

        // 发送请求
        $dataReturn = curl_exec($curlRequest);
        // 如果有错误则大于0 成功等于0
        if (curl_errno($curlRequest) > 0) {
            for ($i = 0; $i < $retries; $i++) {
                $dataReturn = curl_exec($curlRequest);
                if (curl_errno($curlRequest) == 0) {
                    break;
                }

                if (curl_errno($curlRequest) > 0 && $i == $retries - 1) {
                    return 'RequestFailure';
                }

            }
        }
        
        // 关闭
        curl_close($curlRequest);
        return $dataReturn;
    }
}