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
$do = new wapmember;
$session = new dsession();

//已注册用户的跳转链接
$gotologin = $CFG['url'].'member/waplogin.php';
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

$could_emailcode = ($MOD['emailcode_register'] && $DT['mail_type'] != 'close');
$action_sendcode = crypt_action('sendcode');
if($could_emailcode) {
	if($MOD['checkuser'] == 2) $MOD['checkuser'] = 0;
	if($action == $action_sendcode) {
		$email = isset($value) ? trim($value) : '';
		if(!is_email($email)) exit('2');
		if($do->email_exists($email)) exit('3');
		if(!$do->is_email($email)) exit('4');
		isset($_SESSION['email_send']) or $_SESSION['email_send'] = 0;
		if($_SESSION['email_time'] && (($DT_TIME - $_SESSION['email_time']) < 60)) exit('5');
		if($_SESSION['email_send'] > 9) exit('6');
		$emailcode = random(6, '0123456789');
		$_SESSION['email_save'] = $email;
		$_SESSION['email_code'] = md5($email.'|'.$emailcode);
		$_SESSION['email_time'] = $DT_TIME;
		$_SESSION['email_send'] = $_SESSION['email_send'] + 1;
		$title = $L['register_msg_emailcode'];
		$content = ob_template('emailcode', 'mail');
		send_mail($email, $title, stripslashes($content));
		exit('1');
	}
}
$could_mobilecode = ($MOD['mobilecode_register'] && $DT['sms']);
$action_sendscode = crypt_action('sendscode');
if($could_mobilecode) {
	if($action == $action_sendscode) {
		$mobile = isset($value) ? trim($value) : '';
		if(!is_mobile($mobile)) exit('2');
		isset($_SESSION['mobile_send']) or $_SESSION['mobile_send'] = 0;
		if($do->mobile_exists($mobile)) exit('3');
		//if($_SESSION['mobile_time'] && (($DT_TIME - $_SESSION['mobile_time']) < 180)) exit('5');
		if($_SESSION['mobile_send'] > 4) exit('6');
		if(max_sms($mobile)) exit('6');
		$mobilecode = random(6, '0123456789');
		$_SESSION['mobile_save'] = $mobile;
		$_SESSION['mobile_code'] = md5($mobile.'|'.$mobilecode);
		$_SESSION['mobile_time'] = $DT_TIME;
		$_SESSION['mobile_send'] = $_SESSION['mobile_send'] + 1;
		$content = lang('sms->sms_code', array($mobilecode, $MOD['auth_days']*10)).$DT['sms_sign'];
		send_sms($mobile, $content);
		exit('1');
	}
}

//解析URL
$fatherUser = $_GET['fromUserId'];//获得父级userid
$registerPwd = $post["password"];
$registerUserName = $post["username"];
//定义邀请积分
//$MOD['credit_recommand'] = 10;
//end解析URL

$FD = $MFD = cache_read('fields-member.php');
$CFD = cache_read('fields-company.php');
isset($post_fields) or $post_fields = array();
if($MFD || $CFD) require DT_ROOT.'/include/fields.func.php';
$GROUP = cache_read('group.php');
if($submit) {
	if($action != crypt_action('wapregister')) dalert($L['check_sign'].'(1)');
	$post['passport'] = isset($post['passport']) && $post['passport'] ? $post['passport'] : $post['username'];
	if($MOD['passport'] == 'uc') {
		$passport = convert($post['passport'], DT_CHARSET, $MOD['uc_charset']);
		require DT_ROOT.'/api/uc.inc.php';
		list($uid, $rt_username, $rt_password, $rt_email) = uc_user_login($passport, $post['password']);
		if($uid == -2) dalert($L['register_msg_passport'], '', 'parent.Dd("passport").focus();');
	}
	$msg = captcha($captcha, $MOD['captcha_register'], true);
	if($msg) dalert($msg, '', reload_captcha());
	$msg = question($answer, $MOD['question_register'], true);
	if($msg) dalert($msg, '', reload_question());
	if (!is_mobile($post['username']) && !is_email($post['username'])) {
		dalert('用户名必须是手机号或者邮箱...', '', 'parent.Dd("username").focus();');
	}
	$usertype = 0;
	if (is_mobile($post['username'])) {
		$post['mobile'] = $post['username'];
		if (!preg_match("/^[0-9]{6}$/", $post['mobilecode']) || $_SESSION['mobile_code'] != md5($post['mobile'].'|'.$post['mobilecode'])){
			dalert('验证码错误', '', $reload_captcha.$reload_question);
		}
	}else if (is_email($post['username'])) {
		$post['email'] = $post['username'];
		$usertype = 1;
		if (!preg_match("/^[0-9]{6}$/", $post['mobilecode']) || $_SESSION['email_code'] != md5($post['email'].'|'.$post['mobilecode'])){
			dalert('验证码错误', '', $reload_captcha.$reload_question);
		}
	}

	$post['truename'] = $post['truename']; //真实姓名默认为用户名
	$RG = array();
	foreach($GROUP as $k=>$v) {
		if($k > 4 && $v['vip'] == 0) $RG[] = $k;
	}
	$reload_captcha = $MOD['captcha_register'] ? reload_captcha() : '';
	$reload_question = $MOD['question_register'] ? reload_question() : '';
	in_array($post['regid'], $RG) or dalert($L['register_pass_groupid'], '', $reload_captcha.$reload_question);
	// if($could_emailcode) {
	// 	if(!preg_match("/^[0-9]{6}$/", $post['emailcode']) || $_SESSION['email_code'] != md5($post['email'].'|'.$post['emailcode'])) dalert($L['register_pass_emailcode'], '', $reload_captcha.$reload_question);
	// }
	// if($could_mobilecode) {
	// 	if(!preg_match("/^[0-9]{6}$/", $post['mobilecode']) || $_SESSION['mobile_code'] != md5($post['mobile'].'|'.$post['mobilecode'])) dalert($L['register_pass_mobilecode'], '', $reload_captcha.$reload_question);
	// }
	// if (!preg_match("/^[0-9]{6}$/", $post['mobilecode']) || $_SESSION['mobile_code'] != md5($post['mobile'].'|'.$post['mobilecode']) || !preg_match("/^[0-9]{6}$/", $post['mobilecode']) || $_SESSION['email_code'] != md5($post['email'].'|'.$post['mobilecode'])) {
	// 	dalert('验证码错误', '', $reload_captcha.$reload_question);
	// }
	if($post['regid'] == 5) $post['company'] = $post['username'];
	$post['groupid'] = $MOD['checkuser'] ? 4 : $post['regid'];
	$post['content'] = $post['introduce'] = $post['thumb'] = $post['banner'] = $post['catid'] = $post['catids'] = '';
	$post['edittime'] = 0;
	$inviter = get_cookie('inviter');
	$post['inviter'] = $inviter ? decrypt($inviter, DT_KEY.'INVITER') : '';
	check_name($post['inviter']) or $post['inviter'] = '';
	if($do->add($post)) {
		$userid = $do->userid;
		$username = $post['username'];
		$email = $post['email'];
		if($MFD) fields_update($post_fields, $do->table_member, $userid, 'userid', $MFD);
		if($CFD) fields_update($post_fields, $do->table_company, $userid, 'userid', $CFD);
		if($MOD['passport'] == 'uc') {
			$uid = uc_user_register($passport, $post['password'], $post['email']);
			if($uid > 0 && $MOD['uc_bbs']) uc_user_regbbs($uid, $passport, $post['password'], $post['email']);
		}
		//send sms
		if($MOD['welcome_sms'] && $DT['sms'] && is_mobile($post['mobile'])) {
			$message = lang('sms->wel_reg', array($post['truename'], $DT['sitename'], $post['username'], $post['password']));
			$message = strip_sms($message);
			send_sms($post['mobile'], $message);
		}
		//send sms
		if($MOD['checkuser'] == 2) {
			$goto = 'send.php?action=check&auth='.encrypt($email.'|'.$DT_TIME, DT_KEY.'REG');
			dalert('', '', 'parent.window.location="'.$goto.'";');
		} else if($MOD['checkuser'] == 1) {
			$forward = $MOD['linkurl'];
		} else if($MOD['checkuser'] == 0) {
			if($MOD['welcome_message'] || $MOD['welcome_email']) {
				$title = $L['register_msg_welcome'];
				$content = ob_template('welcome', 'mail');
				if($MOD['welcome_message']) send_message($username, $title, $content);
				//if($MOD['welcome_email'] && $DT['mail_type'] != 'close') send_mail($email, $title, $content);
			}
		}
		//if($could_emailcode) $db->query("UPDATE {$DT_PRE}member SET vemail=1 WHERE username='$username'");
		if($usertype == 1){
			$db->query("UPDATE {$DT_PRE}member SET vemail=1 WHERE username='$username'");
		}else{
			$db->query("UPDATE {$DT_PRE}member SET vmobile=1 WHERE username='$username'");
		}

		if($fatherUser != null)
		{
			$username = $post['username'];
			$fatherUser = (int)$fatherUser;
			$post['invitecode'] = (int)$fatherUser;//将url中string类型的invitecode转化为int
			$result = $db->query("UPDATE {$db->pre}member SET INVITECODE = $fatherUser WHERE USERNAME = '$username'");
			//获取父级userid的用户，将其积分加10分
			$result = $db->query("SELECT * FROM {$db->pre}member WHERE USERID = $fatherUser LIMIT 1");
			while($r=$db->fetch_array($result))
			{
				$memberinfo[] = $r;
			}
			credit_add($memberinfo[0]['username'], $MOD['credit_recommand']);
			credit_record($memberinfo[0]['username'], $MOD['credit_recommand'], 'system', $L['member_record_recommand'], $DT_IP);
		}
		//END获取父级userid的用户，将其积分加10分

		//注册成功，自动登录
		$cookietime = isset($cookietime) ? 86400*30 : 0;
		$user = $do->login($username, $registerPwd, $cookietime);
		//注册成功，自动登录

		//通过session记录登录的用户名
//		session_start();
//		$_SESSION['wapuser'] = $registerUserName;//注册session
//		$_SESSION['wappwd'] = $registerPwd;//注册session
//		$tempuid = $db->get_one("SELECT userid FROM {$db->pre}member WHERE username = '$registerUserName' LIMIT 1");
//		$_SESSION['wapuid'] = $tempuid['userid'];//注册session
//		$_SESSION['zhouyi']="zhouyi";
		//通过session记录登录的用户名

		//注册成功发送短信模块++
		$successsms = "用户您好，欢迎您注册仪表堂，您的登录用户名为:".$registerUserName.",密码为:".$registerPwd."。登录网站:www.yibiaotang.com或关注官方微信号ybt-2016查看积分变化。";
		$content = $successsms.$DT['sms_sign'];
		send_sms($registerUserName, $content);
		//注册成功发送短信模块--

		$forward = $CFG['url'].'member/wapregsucc.php?flag=1';
		dalert('', '', 'parent.window.location="'.$forward.'"');
	} else {
		$reload_captcha = $MOD['captcha_register'] ? reload_captcha() : '';
		$reload_question = $MOD['question_register'] ? reload_question() : '';
		dalert($do->errmsg, '', $reload_captcha.$reload_question);
	}
} else {
	//if($DT_TOUCH) dheader($EXT['mobile_url'].'register.php?forward='.urlencode($forward));
	$COM_TYPE = explode('|', $MOD['com_type']);
	$COM_SIZE = explode('|', $MOD['com_size']);
	$COM_MODE = explode('|', $MOD['com_mode']);
	$MONEY_UNIT = explode('|', $MOD['money_unit']);
	$mode_check = dcheckbox($COM_MODE, 'post[mode][]', '', 'onclick="check_mode(this);"', 0);
	isset($auth) or $auth = '';
	$username = $password = $email = $passport = '';
	if($auth) {
		$auth = decrypt($auth, DT_KEY.'UC');
		$auth = explode('|', $auth);
		$passport = $auth[0];
		if(check_name($passport)) $username = $passport;
		$password = $auth[1];
		$email = is_email($auth[2]) ? $auth[2] : '';
		if($email) $_SESSION['regemail'] = md5(md5($email.DT_KEY.$DT_IP));
	}
	$areaid = $cityid;
	set_cookie('forward_url', $forward);
	$head_title = $L['register_title'];
	include template('wapregister', $module);
}
?>