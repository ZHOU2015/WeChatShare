<?php 
defined('IN_DESTOON') or exit('Access Denied');
//if($_userid && !$MOD['passport']) dheader($MOD['linkurl']);
require DT_ROOT.'/module/'.$module.'/common.inc.php';
require DT_ROOT.'/include/post.func.php';
require DT_ROOT.'/wechatcomm.class.php';

//取session:

if(isset($_username)){
	$username = $_username;
	$uid = $_userid;
}
else{
	$username = '';
	$uid = '';
}
//取session:
$memberinfo = array();
if($uid)
{
	$result = $db->query("SELECT * FROM {$db->pre}member WHERE userid = '$uid' LIMIT 1;");
}

while($r=$db->fetch_array($result))
{
	$memberinfo[] = $r;
}
$userid = $memberinfo[0]["userid"];
$username = $memberinfo[0]["username"];
$point = $memberinfo[0]["credit"];
$inviteno = $db->get_one("SELECT COUNT(*) as SUM FROM {$db->pre}member WHERE INVITECODE = $userid");
$suminvite = $inviteno["SUM"];


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

	include template('waploginsucc', $module);
?>