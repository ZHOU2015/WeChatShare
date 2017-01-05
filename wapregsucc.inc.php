<?php
defined('IN_DESTOON') or exit('Access Denied');
//if($_userid) dheader($MOD['linkurl']);
require DT_ROOT.'/module/'.$module.'/common.inc.php';
if(isset($read)) exit(include template('agreement', $module));
if(!$MOD['enable_register']) message($L['register_msg_close'], DT_PATH);
if($MOD['defend_proxy']) {
	if($_SERVER['HTTP_X_FORWARDED_FOR'] || $_SERVER['HTTP_VIA'] || $_SERVER['HTTP_PROXY_CONNECTION'] || $_SERVER['HTTP_USER_AGENT_VIA'] || $_SERVER['HTTP_CACHE_INFO'] || $_SERVER['HTTP_PROXY_CONNECTION']) {
		message(lang('include->defend_proxy'));
	}
}
if($MOD['banagent']) {
	$banagent = explode('|', $MOD['banagent']);
	foreach($banagent as $v) {
		if(strpos($_SERVER['HTTP_USER_AGENT'], $v) !== false) message($L['register_msg_agent'], DT_PATH, 5);
	}
}
if($MOD['iptimeout']) {
	$timeout = $DT_TIME - $MOD['iptimeout']*3600;
	$r = $db->get_one("SELECT userid FROM {$DT_PRE}member WHERE regip='$DT_IP' AND regtime>'$timeout'");
	if($r) message(lang($L['register_msg_ip'], array($MOD['iptimeout'])), DT_PATH);
}
if($DT['mail_type'] == 'close' && $MOD['checkuser'] == 2) $MOD['checkuser'] = 0;
require DT_ROOT.'/include/post.func.php';
require MD_ROOT.'/wapmember.class.php';
require DT_ROOT.'/wechatcomm.class.php';
//取session
//echo  $_userid;   // 这个是用户ID
//echo '<hr>';
/*b*/
//echo $_username;  //用户名称-*/+\09876dfvb n,l.58
//echo '<hr>';
//session_start();
//var_dump($_SESSION);die();
//判断是否是从注册页面过来的
$flag = $_GET['flag'];

if(isset($_username)){
	$username = $_username;
	$userid = $_userid;
}
else{
	$username = '';
	$userid = '';
}
//取session

//微信分享
//缓存access_token，读取缓存access_token
$wccomm = new wechatcomm();
$token = null;
$jsapi = null;
$token_info = $db->get_one("SELECT * FROM {$db->pre}wechat_token");
$time = time();
if ($token_info) {
	$id = $token_info["id"];
	if (time() > $token_info["edittime"] + 7200) {
		$token = $wccomm->getAccessToken();
		$jsapi = $wccomm->getHtml5JsTicket($token);
		$res = $db->query("UPDATE {$DT_PRE}wechat_token SET token='$token',jsapi='$jsapi', edittime=$time WHERE id=$id");
	} else {
		$token = $token_info["token"];
		$jsapi = $token_info["jsapi"];
	}
} else {
	$token = $wccomm->getAccessToken();
	$jsapi = $wccomm->getHtml5JsTicket($token);
	$res = $db->query("INSERT INTO {$DT_PRE}wechat_token (id,token,jsapi,edittime) VALUES ('1','$token','$jsapi',$time)");
}
//缓存access_token，读取缓存access_token
$arr = $wccomm->shareFriend(substr($CFG['url'], 0, -1),$jsapi);

$prize_total = $db->get_one("SELECT COUNT(*) AS num FROM {$db->pre}gift");
if($prize_total["num"] != "0")
{
	$A=array();
	$result_prize = $db->query("SELECT * FROM {$db->pre}gift");

	while($r=$db->fetch_array($result_prize)){
		$A[]=$r;
	}
}

//解析URL
$fatherUser = $_GET['fromUserId'];//获得父级userid
$registerPwd = $post["password"];
$registerUserName = $post["username"];
//end解析URL

$FD = $MFD = cache_read('fields-member.php');
$CFD = cache_read('fields-company.php');
isset($post_fields) or $post_fields = array();
if($MFD || $CFD) require DT_ROOT.'/include/fields.func.php';
$GROUP = cache_read('group.php');
include template('wapregsucc', $module);
?>