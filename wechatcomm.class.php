<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/16
 * Time: 10:53
 */
defined('IN_DESTOON') or exit('Access Denied');

class wechatcomm
{
    /**
     * 分享到朋友圈
     */
    function shareFriend($headerurl,$jsapi)
    {
        //分享代码
        $arr = array();
        $arr['jsapi_ticket'] = $jsapi;
        $arr['noncestr'] = $this->wxGetNoncestr();
        $arr['timestamp'] = time();
        $arr['url'] = $headerurl.$_SERVER["REQUEST_URI"];
        ksort($arr);
        $sign_str = array();
        foreach ($arr as $key => $value) {
            $sign_str[] = $key . '=' . $value;
        }
        $sign_str = implode('&', $sign_str);
        $sign_str = sha1($sign_str);
        $arr['signature'] = $sign_str;
        return $arr;
    }

//curl获取请求文本内容
    function execCurl($method, $args)
    {
        //解析参数
        $url = isset($args[0]) ? $args[0] : "";
        $multi = isset($args[1]['multi']) ? $args[1]['multi'] : "";
        $data = isset($args[1]['data']) ? $args[1]['data'] : "";
        $ajax = isset($args[1]['ajax']) ? $args[1]['ajax'] : "";
        $timeout = isset($args[1]['timeout']) ? $args[1]['timeout'] : 30;
        $files = isset($args[1]['files']) ? $args[1]['files'] : "";
        $referer = isset($args[1]['referer']) ? $args[1]['referer'] : "";
        $proxy = isset($args[1]['proxy']) ? $args[1]['proxy'] : "";
        $headers = isset($args[1]['headers']) ? $args[1]['headers'] : "";

        //如果环境变量的浏览器信息不存在，就是用手动设置的浏览器信息
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ?
            $_SERVER['HTTP_USER_AGENT'] :
            'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:23.0)
                    Gecko/20100101 Firefox/23.0';

        //检测url必须参数 不能为空
        if (!$url) {
            throw new \Exception("错误：curl请求地址不能为空");
        }

        //设置curl选项
        $options = array(
            CURLOPT_URL => $url,      //目标url
            CURLOPT_TIMEOUT => $timeout,  //超时
            CURLOPT_RETURNTRANSFER => 1,         //输出数据流
            CURLOPT_FOLLOWLOCATION => 1,         //自动跳转追踪
            CURLOPT_AUTOREFERER => 1,         //自动设置来路信息
            CURLOPT_SSL_VERIFYPEER => 0,         //认证证书检查
            CURLOPT_SSL_VERIFYHOST => 0,         //检查SSL加密算法
            CURLOPT_HEADER => 0,         //禁止头文件输出
            CURLOPT_NOSIGNAL => 1,         //忽略所有传递的信号
            CURLOPT_USERAGENT => $userAgent,//浏览器环境字符串
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, //ipv4寻址方式
            CURLOPT_ENCODING => 'gzip',    //解析使用gzip压缩的网页
        );


        //设置代理 必须是数组并且非空
        if (is_array($proxy) && !empty($proxy)) {
            $options[CURLOPT_PROXY] = $proxy['host'];
            $options[CURLOPT_PROXYPORT] = $proxy['port'];
            $options[CURLOPT_PROXYUSERPWD] =
                $proxy['user'] . ':' . $proxy['pass'];
        }

        //检测是否未启用自定义urlencode编码
        if (!isset($args[1]['build'])) {
            if ($data && $method == "post" && is_array($data)) {
                $data = http_build_query($data, '', '&');
            }
        }

        //检测是否含有上传文件
        if ($files && $method == "post" && is_array($files)) {
            foreach ($files as $k => $v) {
                $files[$k] = '@' . $v;
            }

            parse_str($data, $data);
            $data = $data + $files;
        }

        //检测判断是否是post请求
        if ($method == 'post') {
            //发送一个常规的POST请求
            $options[CURLOPT_POST] = 1;

            //使用HTTP协议中的"POST"操作来发送数据,支持键值对数组定义
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        //初始化header数组
        $headerOptions = array();

        //检测是否是ajax提交
        if ($ajax) {
            $headerOptions['X-Requested-With'] = 'XMLHttpRequest';
        }

        //设置来路
        if ($referer) {
            $options[CURLOPT_REFERER] = $referer;
        }

        //合并header
        if (!empty($headers) && is_array($headers)) {
            foreach ($headers as $k => $v) {
                $headerOptions[$k] = $v;
            }
        }

        //转换header选项为浏览器header格式
        if (!empty($headerOptions) && is_array($headerOptions)) {
            $array = array();

            foreach ($headerOptions as $k => $v) {
                $array[] = $k . ": " . $v;
            }

            $options[CURLOPT_HTTPHEADER] = $array;
        }

        //创建curl句柄
        $ch = curl_init();

        //设置curl选项
        curl_setopt_array($ch, $options);

        //获取返回内容
        $content = curl_exec($ch);

        //关闭curl句柄
        curl_close($ch);

        //返回内容
        return $content;

    }

    function getHtml5JsTicket($token)
    {
        //html5_js_ticket 记得做个缓存
        //$access_token = $this->getAccessToken();
        $access_token = $token;
        //获取后 取得 jsapi渠道
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $access_token . '&type=jsapi';
        $info = $this->execCurl('get', array($url));
        $info = json_decode($info, true);
        if ($info['errcode'] === 0) {
            return $info['ticket'];
        }
        return false;
    }

    function wxGetNoncestr($len = 32)
    {
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $str = '';
        for ($i = 1; $i <= $len; $i++) {
            $key = rand(0, count($chars));
            $str .= @$chars[$key];
        }
        return $str;
    }


    /**
     * 获取AccessToken
     */
    function getAccessToken()
    {
        //access_token 记得缓存
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=&secret=";//此处写入微信公众号的appid和secret
        $info = $this->execCurl('get', array($url));
        $list = json_decode($info, true);
        return $list['access_token'];
    }
//end微信分享
}

?>