<?php 
defined('IN_DESTOON') or exit('Access Denied');
//if($_userid && !$MOD['passport']) dheader($MOD['linkurl']);
require DT_ROOT.'/module/'.$module.'/common.inc.php';
require MD_ROOT.'/wapmember.class.php';
require DT_ROOT.'/include/post.func.php';
require DT_ROOT.'/wechatcomm.class.php';
$do = new wapmember;
$forward = $CFG['url']."member/wapregsucc.php";//$forward ? linkurl($forward) : DT_PATH;
if($submit && $MOD['captcha_login'] && strlen($captcha) < 4) $submit = false;
isset($auth) or $auth = '';
if($_userid) $auth = '';
if($auth) {
	$auth = decrypt($auth, DT_KEY.'LOGIN');
	$_auth = explode('|', $auth);
	if($_auth[0] == 'LOGIN' && check_name($_auth[1]) && strlen($_auth[2]) >= $MOD['minpassword'] && $DT_TIME >= intval($_auth[3]) && $DT_TIME - intval($_auth[3]) < 30) {
		$submit = 1;
		$username = $_auth[1];
		$password = $_auth[2];
		$MOD['captcha_login'] = $captcha = 0;
	}
}

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

//微信分享
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
$username=$_POST['usrname'];
$pwd = $_POST['pwd'];

//ajax验证
if($action=="check")
{
	$option = isset($option) ? trim($option) : 'username';
	$r = $db->get_one("SELECT username,password,passsalt,passport,userid FROM {$DT_PRE}member WHERE `$option`='$username'");
	if($r) {
		$username = $r['username'];
		$passport = $r['passport'];
		$passsalt = $r['passsalt'];
		$password = $r['password'];
	}
	if((dpassword($pwd, $passsalt)) != $password){
		exit("2");
	}
	else{
		captcha($captcha, $MOD['captcha_login']);
		if(strlen($username) < 3) message($L['login_msg_username']);
		if(strlen($pwd) < 5) message($L['login_msg_password']);
		$goto = isset($goto) ? true : false;
		if($goto) $forward = $MOD['linkurl'];
		$cookietime = isset($cookietime) ? 86400*30 : 0;
		$api_msg = $api_url = '';
		$option = isset($option) ? trim($option) : 'username';
		// if(is_email($username) && $option == 'username') $option = 'email';
		// if(!check_name($username) && $option == 'username') $option = 'passport';
		in_array($option, array('username', 'passport', 'email', 'mobile', 'company', 'userid')) or $option = 'username';
		$r = $db->get_one("SELECT username,passport,userid FROM {$DT_PRE}member WHERE `$option`='$username'");
		if($r) {
			$username = $r['username'];
			$passport = $r['passport'];
			$passsalt = $r['passsalt'];
			$password = $r['password'];
			$userid = $r['userid'];
			//通过session记录登录的用户名
//			session_start();
//			$_SESSION['wapuser'] = $username;//注册session
//			$_SESSION['wappwd'] = $password;//注册session
//			$_SESSION['wapuid'] = $userid;//注册session
			//通过session记录登录的用户名
		} else {
			if($option == 'username' || $option == 'passport') {
				$passport = $username;
				if($option == 'username' && $MOD['passport']) {
					$r = $db->get_one("SELECT username FROM {$DT_PRE}member WHERE `passport`='$username'");
					if($r) $username = $r['username'];
				}
			} else {
				message($L['login_msg_not_member']);
			}
		}
		if($MOD['passport'] == 'uc') include DT_ROOT.'/api/'.$MOD['passport'].'.inc.php';
		$user = $do->login($username, $pwd, $cookietime);
		if($user) {
			if($MOD['passport'] && $MOD['passport'] != 'uc') {
				$api_url = '';
				$user['password'] = is_md5($pwd) ? $pwd : md5($pwd);//Once MD5
				if(strtoupper($MOD['passport_charset']) != DT_CHARSET) $user = convert($user, DT_CHARSET, $MOD['passport_charset']);
				extract($user);
				include DT_ROOT.'/api/'.$MOD['passport'].'.inc.php';
				if($api_url) $forward = $api_url;
			}
			#if($MOD['sso']) include DT_ROOT.'/api/sso.inc.php';
			if($DT['login_log'] == 2) $do->login_log($username, $pwd, $user['passsalt'], 0);
//			if($api_msg) message($api_msg, $forward, -1);
//			message($api_msg, $forward);
		} else {
			if($DT['login_log'] == 2) $do->login_log($username, $pwd, $user['passsalt'], 0, $do->errmsg);
			message($do->errmsg);
		}
		exit("1");
	}
}
else{
	//if($DT_TOUCH) dheader($EXT['mobile_url'].'login.php?forward='.urlencode($forward));
	isset($username) or $username = $_username;
	isset($password) or $password = '';
	$register = isset($register) && $username ? 1 : 0;
	$username or $username = get_cookie('username');
	check_name($username) or $username = '';
	$OAUTH = cache_read('oauth.php');
	$oa = 0;
	foreach($OAUTH as $v) {
		if($v['enable']) {
			$oa = 1;
			break;
		}
	}
	set_cookie('forward_url', $forward);
	$head_title = $register ? $L['login_title_reg'] : $L['login_title'];
	include template('waplogin', $module);
}
//ajax验证

if($submit) {
//	captcha($captcha, $MOD['captcha_login']);
//	$username = trim($username);
//	$password = trim($pwd);
//	if(strlen($username) < 3) message($L['login_msg_username']);
//	if(strlen($password) < 5) message($L['login_msg_password']);
//	$goto = isset($goto) ? true : false;
//	if($goto) $forward = $MOD['linkurl'];
//	$cookietime = isset($cookietime) ? 86400*30 : 0;
//	$api_msg = $api_url = '';
//	$option = isset($option) ? trim($option) : 'username';
//	// if(is_email($username) && $option == 'username') $option = 'email';
//	// if(!check_name($username) && $option == 'username') $option = 'passport';
//	in_array($option, array('username', 'passport', 'email', 'mobile', 'company', 'userid')) or $option = 'username';
//	$r = $db->get_one("SELECT username,passport,userid FROM {$DT_PRE}member WHERE `$option`='$username'");
//	if($r) {
//		$username = $r['username'];
//		$passport = $r['passport'];
//
//		$password = $r['password'];
//		$userid = $r['userid'];
//		//通过session记录登录的用户名
//		session_start();
//		$_SESSION['wapuser'] = $username;//注册session
//		$_SESSION['wappwd'] = $password;//注册session
//		$_SESSION['wapuid'] = $userid;//注册session
//		//通过session记录登录的用户名
//	} else {
//		if($option == 'username' || $option == 'passport') {
//			$passport = $username;
//			if($option == 'username' && $MOD['passport']) {
//				$r = $db->get_one("SELECT username FROM {$DT_PRE}member WHERE `passport`='$username'");
//				if($r) $username = $r['username'];
//			}
//		} else {
//			message($L['login_msg_not_member']);
//		}
//	}
//	if($MOD['passport'] == 'uc') include DT_ROOT.'/api/'.$MOD['passport'].'.inc.php';
//	$user = $do->login($username, $password, $cookietime);
//	if($user) {
//		if($MOD['passport'] && $MOD['passport'] != 'uc') {
//			$api_url = '';
//			$user['password'] = is_md5($password) ? $password : md5($password);//Once MD5
//			if(strtoupper($MOD['passport_charset']) != DT_CHARSET) $user = convert($user, DT_CHARSET, $MOD['passport_charset']);
//			extract($user);
//			include DT_ROOT.'/api/'.$MOD['passport'].'.inc.php';
//			if($api_url) $forward = $api_url;
//		}
//		#if($MOD['sso']) include DT_ROOT.'/api/sso.inc.php';
//		if($DT['login_log'] == 2) $do->login_log($username, $password, $user['passsalt'], 0);
//		if($api_msg) message($api_msg, $forward, -1);
//		message($api_msg, $forward);
//	} else {
//		if($DT['login_log'] == 2) $do->login_log($username, $password, $user['passsalt'], 0, $do->errmsg);
//		message($do->errmsg);
//	}
} else {
	//if($DT_TOUCH) dheader($EXT['mobile_url'].'login.php?forward='.urlencode($forward));
//	isset($username) or $username = $_username;
//	isset($password) or $password = '';
//	$register = isset($register) && $username ? 1 : 0;
//	$username or $username = get_cookie('username');
//	check_name($username) or $username = '';
//	$OAUTH = cache_read('oauth.php');
//	$oa = 0;
//	foreach($OAUTH as $v) {
//		if($v['enable']) {
//			$oa = 1;
//			break;
//		}
//	}
//	set_cookie('forward_url', $forward);
//	$head_title = $register ? $L['login_title_reg'] : $L['login_title'];
//	include template('waplogin', $module);
}
?>