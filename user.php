<?php

/* Авторизация пользователей на сайте */
class user {

	public $auth	= false; // Авторизован ли пользователь
	public $login	= '';
	public $error	= '';
	private $user_table	= 'prefix_users'; // Название таблицы с пользователями в БД
	private $loginfld	= 'elogin'; // Название поля с логином в форме и БД
	private $pwdfld	= 'epasswd'; // Название поля с паролем в форме и БД
	private $userdata	= ' id, name, phone, city, access, subscribe, (SELECT count(*) FROM prefix_messages WHERE `read`="N" AND `to`=u.id) newmessages'; // Инфо из БД, которое нужно записать в сессию
	private $days	= 365; // Сколько дней хранить данные в cookies
	private $authpage	= ''; // Адрес страницы, куда нужно перейти после авторизации. Если пусто - остаёмся на той странице, с которой авторизуемся.
	private $logoutpage	= ''; // Адрес страницы, куда нужно перейти после выхода. Если пусто - вернёмся на ту страницу, откуда был выполнен выход.
	private $secret	= 'a1b2c3d4'; // Добавка к паролю (одинаковая для всех, помимо salt, который для всех разный)

	public function __construct($user_table = '', $loginfld = '', $pwdfld = '', $userdata = '') {

		if($user_table) $this->user_table = $user_table;
		if($loginfld) $this->loginfld = $loginfld;
		if($pwdfld) $this->pwdfld = $pwdfld;
		if($userdata) $this->userdata = $userdata;

		if(isset($_SESSION['id']) && isset($_SESSION[$this->loginfld])) $this->login = $_SESSION[$this->loginfld];
		if($this->login) {
			$this->auth = true;
			db()->query("UPDATE `" . $this->user_table . "` SET lastenter=NOW() WHERE id=" . $_SESSION['id']);
		}

		if($_POST['token']) {
			if($this->social_log_in()) header('Location: ' . ($this->authpage ? $this->authpage : $_SERVER['REQUEST_URI']));
		}

		if(!$this->auth && isset($_POST[$this->loginfld]) && isset($_POST[$this->pwdfld])) {
			if($this->log_in(q($_POST[$this->loginfld]), q($_POST[$this->pwdfld]))) header('Location: ' . ($this->authpage ? $this->authpage : $_SERVER['REQUEST_URI']));
			else $this->error = 'Ошибка! Неправильный логин или пароль!';
		}

		if(!$this->auth && isset($_COOKIE['userlogin']) && isset($_COOKIE['userauth'])) {
			if($this->auto_log_in(q($_COOKIE['userlogin']), q($_COOKIE['userauth']))) header('Location: ' . $_SERVER['REQUEST_URI']);
		}

	}

	/* Авторизация */
	public function log_in($login, $passwd) {

		if($login && $passwd) { // Пустые логин и пароль не пройдут!

			$info = db()->query_first("SELECT " . ($this->userdata ? $this->userdata : "1") . " FROM `" . $this->user_table . "` u WHERE `" . $this->loginfld . "` = '" . $login . "' AND MID(`" . $this->pwdfld . "`,9,32) = MD5(CONCAT(LEFT(`" . $this->pwdfld . "`,8),'" . $passwd . $this->secret . "'))");

			if(!empty($info)) {

				$_SESSION[$this->loginfld] = $login;
				if($this->userdata) {
					foreach($info as $k=>$val) $_SESSION[$k] = $val;
				}
				$this->auth = true;
				if(isset($_POST['galka'])) { // Запоминаем пользователя в cookies, если он разрешил автоматический вход
					setcookie('userlogin', $login, time()+86400*$this->days, '/');
					setcookie('userauth', md5($passwd), time()+86400*$this->days, '/');
				}
				return true;

			} else return false;

		} else return false;

	}

	/* Авторизация через данные cookies */
	public function auto_log_in($login, $authstr) {

		if(!$this->auth && $login && $authstr) { // Пустые логин и пароль не пройдут!

			$info = db()->query_first("SELECT " . ($this->userdata ? $this->userdata : "1") . " FROM `" . $this->user_table . "` u WHERE `" . $this->loginfld . "` = '" . $login . "' AND MD5(`" . $this->pwdfld . "`) = '" . $authstr . "'");

			if(!empty($info)) {

				$_SESSION[$this->loginfld] = $login;
				if($this->userdata) {
					foreach($info as $k=>$val) $_SESSION[$k] = $val;
				}
				$this->auth = true;
				setcookie('userlogin', $login, time()+86400*$this->days, '/'); // Продлеваем время
				setcookie('userauth', $authstr, time()+86400*$this->days, '/'); // жизни cookies
				return true;

			} else return false;

		} else return false;

	}

	/* Авторизация через соцсети через сервис ulogin.ru */
	public function social_log_in() {

		$s = file_get_contents('http://ulogin.ru/token.php?token=' . $_POST['token'] . '&host=' . $_SERVER['HTTP_HOST']);
		if($u = json_decode($s, true)) {
			if(!$u['email']) $u['email'] = $u['manual']['email'];
			if(!$u['first_name']) $u['first_name'] = $u['manual']['first_name'];
			if(!$u['last_name']) $u['last_name'] = $u['manual']['last_name'];
			if(!$this->log_in($u['email'], '', true)) {
				$_POST['name'] = trim($u['last_name'].' '.$u['first_name']); // данные пользователя из соцсети
				$_POST['phone'] = $u['phone']; // данные пользователя из соцсети
				$passwd = substr(md5($u['email'].'@'),rand(1,23),8); // генерируем случайный пароль
				if($this->register($u['email'], $passwd, true)) {
					$body  = "Вы успешно зарегистрированы на сайте " . $_SERVER['SERVER_NAME'] . "\r\n";
					$body .= "Ваш логин: " . $u['email'] . "\r\n";
					$body .= "Ваш пароль: " . $passwd . "\r\n";
					$mail = new ZFmail($u['email'], 'noreply@'.$_SERVER['SERVER_NAME'], 'Регистрация на сайте '.$_SERVER['SERVER_NAME'], $body);
					$mail->send();
					$this->log_in($u['email'], '', true);
				}
			}
			return true;
		} else return false;

	}

	/* Разлогинивание */
	public function log_out() {

		if($this->auth) {
			unset($_SESSION[$this->loginfld]);
			if($this->userdata) foreach(explode(',', $this->userdata) as $userfld) unset($_SESSION[trim(strrchr($userfld,' '),'` ')]);
			$this->login = '';
			setcookie('userlogin', '', 0, '/'); // Удаляем cookies
			setcookie('userauth', '', 0, '/'); // если были
			$this->auth = false;
		}

		header('Location: ' . ($this->logoutpage ? $this->logoutpage : ($_SERVER['HTTP_REFERER'] && substr($_SERVER['HTTP_REFERER'], -6)!='logout' ? $_SERVER['HTTP_REFERER'] : '/')));
		exit();

	}

	/* Регистрация */
	public function register($login, $passwd) {

		$salt = substr(md5($login),rand(1,23),8);
		$db = db();
		$db->query("INSERT INTO `" . $this->user_table . "` SET
			`" . $this->loginfld . "` = '" . $login . "',
			`" . $this->pwdfld . "` = CONCAT('" . $salt . "',MD5('" . $salt . $passwd . $this->secret . "'),'" . dechex(rand(0x10000000,0x7FFFFFFF)) . "'),
			`name` = '" . q($_POST['name']) . "',
			`phone` = '" . q($_POST['phone']) . "',
			`city` = '" . q($_POST['city']) . "',
			`access` = '" . ($_POST['access'] ? 'Y' : 'N') . "',
			`created` = NOW()
		");
		if($db->error_string) return false;
		else return true;

	}

	/* Восстановление пароля */
	public function change_pass($login, $oldpwd = '', $passwd = '') {

		$salt = substr(md5($login),rand(1,23),8);
		if(!$passwd) $passwd = substr(md5($login.'@'),rand(1,23),8); // при восстановлении генерируем случайный пароль
		$db = db();
		$db->query("UPDATE `" . $this->user_table . "` SET
			`" . $this->pwdfld . "` = CONCAT('" . $salt . "',MD5('" . $salt . $passwd . $this->secret . "'),'" . dechex(rand(0x10000000,0x7FFFFFFF)) . "'),
			`modified` = NOW()
			WHERE `" . $this->loginfld . "` = '" . $login . "'
			" . ($oldpwd ? " AND MID(`" . $this->pwdfld . "`,9,32) = MD5(CONCAT(LEFT(`" . $this->pwdfld . "`,8),'" . $oldpwd . $this->secret . "'))" : "") . " LIMIT 1");
		if($db->error_string) return false;
		elseif($db->row_count()==1) return $passwd;
		else return false;

	}

}