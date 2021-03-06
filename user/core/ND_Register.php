<?php if (!defined('FROM_BASE')) { header('HTTP/1.1 403 Forbidden'); die('Invalid requested path.'); }

/*
 * This file is part of ND PHP Framework.
 *
 * ND PHP Framework - An handy PHP Framework (www.nd-php.org)
 * Copyright (C) 2015-2016  Pedro A. Hortas (pah@ucodev.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/*
 * ND PHP Framework (www.nd-php.org) - Contributor Agreement
 *
 * When contributing material to this project, please read, accept, sign
 * and send us the Contributor Agreement located in the file NDCA.pdf
 * inside the documentation/ directory.
 *
 */

/*
 * TODO:
 *
 * - ND JSON calls for check VAT are implemented, but ND JSON service is unavailable
 * - ND SMS Service is unavailable (but code is implemented)
 * - Due to ND JSON and ND SMS unavailability, SMS and VAT confirmation are not available (code implemented, but not tested)
 * - SMTP TLS and/or SSL support needs to be fixed.
 * - alt tags on input fields are missing (required for accessibility)
 * - A major cleanup/redesign of this controller is required, but probably it'll be cleaned up gradually over the next releases.
 *
 */

class ND_Register extends UW_Controller {
	protected $_author = "ND PHP Framework";	// Project Author
	protected $_project_name = "ND php";
	protected $_tagline = "Framework";
	protected $_description = "An handy PHP Framework";
	protected $_default_timezone = NDPHP_LANG_MOD_DEFAULT_TIMEZONE;
	protected $_default_database = 'default';
	protected $_theme = 'Blueish';
	protected $_base_url = 'http://localhost/ndphp/';
	protected $_temp_dir = '/tmp/';
	protected $_charset = NDPHP_LANG_MOD_DEFAULT_CHARSET;
	protected $_logging = true;
	protected $_accounting = true;

	protected $_word_true = NDPHP_LANG_MOD_WORD_TRUE;
	protected $_word_false = NDPHP_LANG_MOD_WORD_FALSE;

	private $nd_app_base_url = 'https://localhost/ndphp';
	private $roles_regular_id = 4;	/* Regular user roles_id (for newly registered users) */
	private $recaptcha_public_key = '6LxxxxxxAAAAAxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	private $recaptcha_private_key = '6LxxxxxxAAAAAxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	private $nd_sms_from = 'ndphp';
	private $nd_sms_from_no_custom = '0000000000';
	private $nd_sms_confirm_url = 'https://localhost/ndphp/index.php/register/confirm_sms_form/';
	private $nd_mail_confirm_url = 'https://localhost/ndphp/index.php/register/confirm_email_hash/';
	private $nd_mail_from = 'no-reply@nd-php.com';
	private $nd_mail_from_name = 'ND PHP Support Dept.';
	private $nd_mail_subject = 'ND PHP ExampleApp Registration';
	private $nd_mail_smtp_host = 'mail.example.net';
	private $nd_mail_smtp_port = '25';
	private $nd_mail_smtp_user = 'no-reply@nd-php.com';
	private $nd_mail_smtp_pass = 'someUserPassword';
	private $nd_mail_smtp_ssl = '0';
	private $nd_mail_smtp_tls = '0';
	private $nd_credentials_mail_subject = 'ND PHP ExampleApp Recover';


	/*** BEGIN OF NOT AVAILABLE YET ***/
	private $ndsms_acct_name = 'ndsms_acct_name';
	private $ndsms_acct_key = 'ffffffffffffffffffffffffffffffff';
	private $ndsms_acct_name_no_custom = 'ndsms_acct_name_no_custom';
	private $ndsms_acct_key_no_custom = 'ffffffffffffffffffffffffffffffff';
	private $ndsms_rest_sms_url = 'https://cloud.nd-php.com/ndsms/api/index.php/sms/send/';
	private $ndjson_apikey = 'ffffffffffffffffffffffffffffffff';
	private $ndjson_vat_url = 'https://cloud.nd-php.com/ndjson/index.php/vat/check/$!ndjson_apikey!$';
	/*** END OF NOT AVAILABLE YET ***/


	private $register_confirm_vat_eu = '0'; // '0';
	private $register_with_recaptcha = '0'; // '0';
	private $register_confirm_email  = '1'; // '1';
	private $register_confirm_phone  = '0'; // '0';

	protected function _get_theme() {
		$this->db->select(
			'themes.theme AS name,'.
			'themes.animation_default_delay AS animation_default_delay,themes.animation_ordering_delay AS animation_ordering_delay,'.
			'themes_animations_default.animation AS animation_default_type,themes_animations_ordering.animation AS animation_ordering_type'
		);
		$this->db->from('themes');
		$this->db->join('themes_animations_default', 'themes_animations_default.id = themes.themes_animations_default_id', 'left');
		$this->db->join('themes_animations_ordering', 'themes_animations_ordering.id = themes.themes_animations_ordering_id', 'left');
		$this->db->where('theme', $this->_theme);
		$q = $this->db->get();

		return $q->row_array();
	}

	protected function _get_features() {
		return $this->features->get_features();
	}

	/* Constructor */
	public function __construct()
	{
		parent::__construct();

		/* Load configuration */
		$config = $this->configuration->get();

		$this->_base_url = $config['base_url'];
		$this->_author = $config['author'];
		$this->_project_name = $config['project_name'];
		$this->_tagline = $config['tagline'];
		$this->_description = $config['description'];
		$this->_default_timezone = $config['timezone'];
		$this->_theme = $config['theme'];
		$this->_temp_dir = $config['temporary_directory'];

		/* Check if we're under maintenance mode */
		if ($config['maintenance'] && !$this->security->im_admin()) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MGMT_UNDER_MAINTENANCE);
		}

		$this->nd_app_base_url = $config['base_url'];
		$this->nd_mail_confirm_url = $config['base_url'] . '/index.php/register/confirm_email_hash/';
		$this->nd_sms_confirm_url = $config['base_url'] . '/index.php/register/confirm_sms_form/';

		$this->nd_mail_smtp_user = $config['smtp_username'];
		$this->nd_mail_smtp_pass = $config['smtp_password'];
		$this->nd_mail_smtp_host = $config['smtp_server'];
		$this->nd_mail_smtp_tls = $config['smtp_tls'] ? '1' : '0'; /* FIXME: If SSL is set, we cannot set TLS. (Convert to drop-down) */
		$this->nd_mail_smtp_ssl = $config['smtp_ssl'] ? '1' : '0'; /* FIXME: If TLS is set, we cannot set SSL. (Convert to drop-down) */

		$this->recaptcha_private_key = $config['recaptcha_priv_key'];
		$this->recaptcha_public_key = $config['recaptcha_pub_key'];

		$this->roles_regular_id = $config['roles_id'];

		/* Features */
		$features = $this->_get_features();

		/* Check if we're under multi or single user mode */
		if (!$features['multi_user']) {
			/* If we're under single user mode, user registration is not available */
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_DISABLED_MULTI_USER);
		}

		/* TODO: FIXME: These private variables should be removed in the future and only the $features array shall be used all around the nd code. */
		$this->register_confirm_vat_eu = $features['register_confirm_vat_eu'];
		$this->register_with_recaptcha = $features['register_with_recaptcha'];
		$this->register_confirm_email = $features['register_confirm_email'];
		$this->register_confirm_phone = $features['register_confirm_phone'];
	}

	private function rand_string($length = 20) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ\\{}()[]#$%&/!*+-';
		$rand_str = '';

		for ($i = 0; $i < $length; $i ++) {
			$rand_str .= $chars[mt_rand(0, strlen($chars) - 1)];
		}

		return $rand_str;
	}

	public function index() {
		$features = $this->_get_features();

		if (!$features['user_registration']) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_DISABLED_USER_REGISTER);
		}

		$this->db->select('id,country,code');
		$this->db->from('countries');
		$this->db->order_by('country', 'asc');

		$query = $this->db->get();

		if (!$query->num_rows()) {
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_UNABLE_REGISTER_NEW_USERS);
		}

		$data = array();

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['features'] = $features;
		$data['config']['theme'] = $this->_get_theme();
		$data['config']['use_recaptcha'] = $this->register_with_recaptcha;
		$data['config']['recaptcha_public_key'] = $this->recaptcha_public_key;

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['title'] = NDPHP_LANG_MOD_REGISTER_USER_REGISTRATION;
		$data['view']['description'] = NDPHP_LANG_MOD_REGISTER_USER_REGISTRATION;
		$data['view']['countries'] = $query;

		$this->load->view('themes/' . $this->_theme . '/' . 'register/register_form', $data);
	}

	public function country_get_prefix($id) {
		$this->db->select('prefix');
		$this->db->from('countries');
		$this->db->where('id', $id);

		$query = $this->db->get();

		$row = $query->row_array();

		echo($row['prefix']);
	}

	public function country_get_code($id) {
		$this->db->select('code');
		$this->db->from('countries');
		$this->db->where('id', $id);
		$this->db->where('eu_state', '1');

		$query = $this->db->get();

		if (!$query->num_rows()) {
			echo('');
			return;
		}

		$row = $query->row_array();

		echo($row['code']);
	}

	private function send_confirmation_email($email, $user_id, $user_id_enc, $first_name, $last_name, $hash) {
		//$this->load->library('phpmailer/phpmailer');

		$this->phpmailer->IsSMTP();
		$this->phpmailer->Host = $this->nd_mail_smtp_host;
		$this->phpmailer->Username = $this->nd_mail_smtp_user;
		$this->phpmailer->Password = $this->nd_mail_smtp_pass;

		if ($this->nd_mail_smtp_user != '')
			$this->phpmailer->SMTPAuth = true;

		if ($this->nd_mail_smtp_ssl != '0')
			$this->phpmailer->SMTPSecure = 'ssl';

		if ($this->nd_mail_smtp_tls != '0')
			$this->phpmailer->SMTPSecure = 'tls';

		$this->phpmailer->From = $this->nd_mail_from;
		$this->phpmailer->FromName = $this->nd_mail_from_name;
		$this->phpmailer->AddAddress(urldecode($email), $first_name . ' ' . $last_name);
		$this->phpmailer->IsHTML(true);

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['theme'] = $this->_get_theme();

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['first_name'] = $first_name;
		$data['view']['users_id_enc'] = $user_id_enc;
		$data['view']['register_confirm_phone'] = $this->register_confirm_phone;
		$data['view']['confirm_sms_url'] = $this->nd_sms_confirm_url . $data['users_id_enc'];
		$data['view']['confirm_email_url'] = $this->nd_mail_confirm_url . $data['users_id_enc'] . '/' . $hash;

		$mail_body = $this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_body', $data, true);

		$this->phpmailer->Subject = $this->nd_mail_subject;
		$this->phpmailer->Body = $mail_body;
		$this->phpmailer->AltBody = strip_tags($mail_body);

		if (!$this->phpmailer->Send()) {
			error_log('register.php: send_confirmation_email(): PHPMailer Send() error: ' . $this->phpmailer->ErrorInfo);
			return false;
		}

		return true;
	}

	private function send_credentials_email($email, $first_name, $last_name, $username, $password) {
		$this->load->library('phpmailer/phpmailer');

		$this->phpmailer->IsSMTP();
		$this->phpmailer->Host = $this->nd_mail_smtp_host;
		$this->phpmailer->Username = $this->nd_mail_smtp_user;
		$this->phpmailer->Password = $this->nd_mail_smtp_pass;

		if ($this->nd_mail_smtp_user != '')
			$this->phpmailer->SMTPAuth = true;

		if ($this->nd_mail_smtp_ssl != '0')
			$this->phpmailer->SMTPSecure = 'ssl';

		if ($this->nd_mail_smtp_tls != '0')
			$this->phpmailer->SMTPSecure = 'tls';

		$this->phpmailer->From = $this->nd_mail_from;
		$this->phpmailer->FromName = $this->nd_mail_from_name;
		$this->phpmailer->AddAddress(urldecode($email), $first_name . ' ' . $last_name);
		$this->phpmailer->IsHTML(true);

		$data = array();

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['theme'] = $this->_get_theme();

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view']['first_name'] = $first_name;
		$data['view']['username'] = $username;
		$data['view']['password'] = $password;

		$mail_body = $this->load->view('themes/' . $this->_theme . '/' . 'register/credentials_email_body', $data, true);

		$this->phpmailer->Subject = $this->nd_credentials_mail_subject;
		$this->phpmailer->Body = $mail_body;
		$this->phpmailer->AltBody = strip_tags($mail_body);

		if (!$this->phpmailer->Send()) {
			error_log('register.php: send_credentials_email(): PHPMailer Send() error: ' . $this->phpmailer->ErrorInfo);
			return false;
		}

		return true;
	}

	private function send_confirmation_sms($phone, $token, $country_custom_sender) {
		$ch = curl_init();

		/* If the country doesn't accept custom sender id, send the message from a real mobile number */
		$sender_id = $country_custom_sender ? $this->nd_sms_from : $this->nd_sms_from_no_custom;

		curl_setopt($ch, CURLOPT_URL, $this->ndsms_rest_sms_url . ($country_custom_sender ? $this->ndsms_acct_name : $this->ndsms_acct_name_no_custom) . '/' . ($country_custom_sender ? $this->ndsms_acct_key : $this->ndsms_acct_key_no_custom) . '/' . $phone . '/' . $token . '/' . $sender_id);
		error_log($this->ndsms_rest_sms_url . $this->ndsms_acct_name . '/' . $this->ndsms_acct_key . '/' . $phone . '/' . $token . '/' . $sender_id);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		if (!curl_exec($ch)) {
			error_log('register.php: send_confirmation_sms(): cURL error: ' . curl_error($ch));
			die(NDPHP_LANG_MOD_FAILED_SEND_SMS);
		}

		$res = curl_multi_getcontent($ch);

		curl_close($ch);

		$res_json = json_decode($res);

		if ($res_json->{'status'} != 'ACCEPTED')
			return FALSE;

		return TRUE;
	}

	protected function register_pre_process(&$POST) {
		return;
	}

	protected function register_post_process($users_id) {
		return;
	}

	public function newuser($ajax = 0) {
		$this->register_pre_process($_POST);

		$users_id = $this->newuser_protected($ajax);

		/* If logging is enabled, log this registration request */
		if ($this->_logging === true) {
			$log_transaction_id = openssl_digest(openssl_random_pseudo_bytes(256), 'sha1');

			$this->db->insert('logging', array(
				'operation' => 'REGISTER',
				'_table' => 'users',
				'transaction' => $log_transaction_id,
				'registered' => date('Y-m-d H:i:s'),
				'users_id' => $users_id
			));
		}

		$this->register_post_process($users_id);
	}

	protected function newuser_protected($ajax = 0) {
		if ($this->register_with_recaptcha == 1) {
			/* Validate reCAPTCHA */
			$res = recaptcha_check_answer($this->recaptcha_private_key, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

			if (!$res->is_valid) {
				header('HTTP/1.1 403 Forbidden');
				die(NDPHP_LANG_MOD_INVALID_RECAPTCHA_VALUE);
			}
		}

		/* Validate First Name */
		if (preg_match("/^[^\ \<\>\%\'\\\"\.\,\;\:\~\^\`\{\[\]\}\?\!\#\&\/\(\)\=\|\\\*\+\-\_\@]+$/", $_POST['first_name']) === false) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_FIRST_NAME);
		}

		/* Validate Last Name */
		if (preg_match("/^[^\ \<\>\%\'\\\"\.\,\;\:\~\^\`\{\[\]\}\?\!\#\&\/\(\)\=\|\\\*\+\-\_\@]+$/", $_POST['last_name']) === false) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_LAST_NAME);
		}

		/* Validate password */
		if (strlen($_POST['password']) < 6) {
			header('HTTP/1.1 403 Forbidden');
			die('Password must have at least 6 characters');
		}

		if (strlen($_POST['password']) > 32) {
			header('HTTP/1.1 403 Forbidden');
			die('Password must have less than 32 characters');
		}

		if ($_POST['password'] != $_POST['password_check']) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INFO_PASSWORD_NO_MATCH);
		}

		if (!isset($_POST['terms']) || ($_POST['terms'] != '1')) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_ATTN_READ_ACCEPT_TERMS);
		}

		/* Validate username */
		if (preg_match('/[a-zA-Z0-9_]{6,32}/', $_POST['username']) === false) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_USERNAME);
		}

		$this->db->select('id');
		$this->db->from('users');
		$this->db->where('username', $_POST['username']);
		$query = $this->db->get();

		if ($query->num_rows()) {
			header("HTTP/1.1 403 Forbidden");
			die(NDPHP_LANG_MOD_INFO_TAKEN_USERNAME);
		}

		/* Validate country */
		$this->db->select('id,code,eu_state,custom_sender');
		$this->db->from('countries');
		$this->db->where('id', intval($_POST['countries_id']));

		$query = $this->db->get();

		if (!$query->num_rows()) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_COUNTRY);
		}

		$row = $query->row_array();

		$countries_id = $row['id'];
		$country_code = $row['code'];
		$country_custom_sender = $row['custom_sender'];

		/* Validate VAT if country is a EU state and if company field was filled */
		if (($row['eu_state'] == '1') && (strlen($_POST['vat']) < 10) && isset($_POST['company']) && (strlen($_POST['company']) >= 2)) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INFO_INCOMPLETE_VAT_EU);
		}

		if ($this->register_confirm_vat_eu == 1) {
			if (isset($_POST['company']) && (strlen($_POST['company']) >= 2) && ($row['eu_state'] == '1')) {
				$vat_json = file_get_contents($this->ndjson_vat_url . '/' . substr($_POST['vat'], 0, 2) . '/' . substr($_POST['vat'], 2));

				$vatinfo = json_decode($vat_json, true);

				if (!$vatinfo || ($vatinfo == array())) {
					header('HTTP/1.1 403 Forbidden');
					die(NDPHP_LANG_MOD_UNABLE_CONFIRM_VAT_EU);
				}

				if ($vatinfo['valid'] !== true) {
					header('HTTP/1.1 403 Forbidden');
					die(NDPHP_LANG_MOD_INVALID_VAT_EU);
				}
			}
		}

		/* Validate email */
		if (preg_match('/^[a-zA-Z0-9\._%\+\-]{1,255}@[a-zA-Z0-9\.\-]{1,255}\.[a-zA-Z]{2,4}$/', $_POST['email']) === false) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_EMAIL);
		}

		$this->db->select('id');
		$this->db->from('users');
		$this->db->where('email', $_POST['email']);
		$query = $this->db->get();

		if ($query->num_rows()) {
			header("HTTP/1.1 403 Forbidden");
			die(NDPHP_LANG_MOD_INFO_EMAIL_REGISTERED);
		}

		if ($this->register_confirm_phone == 1) {
			/* Validate phone */
			if (preg_match('/^\+\d{8,16}$/', $_POST['phone']) === false) {
				header('HTTP/1.1 403 Forbidden');
				die(NDPHP_LANG_MOD_INVALID_PHONE);
			}

			$query = $this->db->query('SELECT *,LENGTH(prefix) AS len FROM countries ORDER BY len DESC,id ASC');

			$valid_phone_prefix = false;

			foreach ($query->result_array() as $row) {
				if (substr($_POST['phone'], 0, $row['len']) == $row['prefix']) {
					if ($countries_id == $row['id']) {
						$valid_phone_prefix = true;
						break;
					}
				}	
			}

			if (!$valid_phone_prefix) {
				header("HTTP/1.1 403 Forbidden");
				die(NDPHP_LANG_MOD_INVALID_PHONE_PREFIX);
			}

			$this->db->select('id');
			$this->db->from('users');
			$this->db->where('phone', $_POST['phone']);
			$query = $this->db->get();

			if ($query->num_rows()) {
				header("HTTP/1.1 403 Forbidden");
				die(NDPHP_LANG_MOD_INFO_PHONE_REGISTERED);
			}

			$this->load->library('libphonenumber/phonenumberutil', '', 'phoneutil'); /* Use libphonenumber for extra accuracy */

			$phone_util = $this->phoneutil->getInstance();
			$phone_nr_proto = $phone_util->parse($_POST['phone'], $country_code);
			$valid_phone = $phone_util->isValidNumber($phone_nr_proto);

			if (!$valid_phone) {
				header("HTTP/1.1 403 Forbidden");
				die(NDPHP_LANG_MOD_INVALID_PHONE);
			}
		}

		/* Setup user data row */
		$userdata['first_name'] = htmlentities($_POST['first_name'], ENT_QUOTES, $this->_charset);
		$userdata['last_name'] = htmlentities($_POST['last_name'], ENT_QUOTES, $this->_charset);
		$userdata['username'] = $_POST['username'];
		$userdata['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT, array('cost' => 10));
		$userdata['email'] = $_POST['email'];
		$userdata['phone'] = $_POST['phone'];

		if (isset($_POST['company']) && ($_POST['company']))
			$userdata['company'] = $_POST['company'];

		$userdata['vat'] = $_POST['vat'];
		$userdata['subscription_types_id'] = 1;
		$userdata['subscription_change_date'] = date('Y-m-d H:i:s');
		$userdata['subscription_renew_date'] = date('Y-m-d', strtotime("+1 month"));
		$userdata['countries_id'] = $_POST['countries_id'];
		$userdata['active'] = 0;
		$userdata['locked'] = 1;
		$userdata['expire'] = '2030-12-31 23:59:59';
		$userdata['registered'] = date('Y-m-d H:i:s');
		$userdata['confirm_email_hash'] = openssl_digest(openssl_random_pseudo_bytes(256), 'sha1');
		$userdata['confirm_phone_token'] = mt_rand(100000, 999999);

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['theme'] = $this->_get_theme();

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['title'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_EMAIL_STATUS;
		$data['view']['description'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_EMAIL_STATUS;

		$this->db->trans_begin();

		$this->db->insert('users', $userdata);
		$users_id = $this->db->last_insert_id(); /* Must be called before trans_status() */

		if ($this->db->trans_status() === false) {
			error_log('register.php: newuser(): Insert failed.');
			$this->db->trans_rollback();
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_FAILED_TRANSACTION . ' #1.');
		} else {
			$users_id_enc = urlencode($this->ndphp->safe_b64encode($this->encrypt->encode($users_id . '.' . mt_rand(100000, 999999))));

			$this->db->trans_commit();

			if ($this->register_confirm_email == 1) {
				/* Send confirmation email */
				if ($this->send_confirmation_email($userdata['email'], $users_id, $users_id_enc, $userdata['first_name'], $userdata['last_name'], $userdata['confirm_email_hash']) !== TRUE) {
					header('HTTP/1.1 500 Internal Server Error');
					die(NDPHP_LANG_MOD_UNABLE_SEND_CONFIRM_EMAIL);
				}
			}

			if ($this->register_confirm_phone == 1) {
				/* Send confirmation sms token */
				if ($this->send_confirmation_sms($userdata['phone'], $userdata['confirm_phone_token'], $country_custom_sender) !== TRUE) {
					header('HTTP/1.1 500 Internal Server Error');
					die(NDPHP_LANG_MOD_UNABLE_SEND_CONFIRM_SMS);
				}

				$this->confirm_sms_form($users_id_enc, $ajax);
			} else if ($this->register_confirm_email == 1) {
				$res = $this->user_try_unlock($users_id);

				if ($res === true) {
					$this->user_active_process($users_id);

					/* FIXME: Create a view file for the following content. (Controller should never deliver html) */

					$data['view']['message'] .= '<br />' . NDPHP_LANG_MOD_REGISTER_USER_ACCT_IS_NOW . ' <span style="font-weight: bold">' . NDPHP_LANG_MOD_WORD_ACTIVE_F . '</span>.<br />';
					$data['view']['message'] .= '<br /><br /><center><a href="' . base_url() . '/index.php/login" class="register_button_link">' . NDPHP_LANG_MOD_LOGIN_LOGIN . '</a></center>';
				} else {
					$data['view']['message'] .= '<br />' . NDPHP_LANG_MOD_REGISTER_CHECK_MOBILE_INBOX . '<br />';
				}

				$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
			} else {
				$res = $this->user_try_unlock($users_id);

				if ($res === true) {
					$this->user_active_process($users_id);

					echo('<br />' . NDPHP_LANG_MOD_REGISTER_USER_ACCT_IS_NOW . ' <span style="font-weight: bold">' . NDPHP_LANG_MOD_WORD_ACTIVE_F . '</span>.<br />');
					echo('<br /><br /><center><a href="' . base_url() . '/index.php/login" class="register_button_link">' . NDPHP_LANG_MOD_LOGIN_LOGIN . '</a></center>');
				} else {
					header('HTTP/1.1 500 Internal Server Error');
					die(NDPHP_LANG_MOD_UNABLE_ACTIVATED_ACCOUNT . ' ' . NDPHP_LANG_MOD_ATTN_CONTACT_SUPPORT . ' #1');
				}
			}
		}

		return $users_id;
	}

	public function recover_password_form() {
		$features = $this->_get_features();

		if (!$features['user_recovery']) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_DISABLED_USER_PASS_RECOVER);
		}

		$this->db->select('id,country,code');
		$this->db->from('countries');
		$this->db->order_by('country', 'asc');

		$query = $this->db->get();

		if (!$query->num_rows()) {
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_UNABLE_RECOVER_CREDENTIALS);
		}

		$data = array();

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['features'] = $features;
		$data['config']['theme'] = $this->_get_theme();
		$data['config']['use_recaptcha'] = $this->register_with_recaptcha;
		$data['config']['recaptcha_public_key'] = $this->recaptcha_public_key;

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['title'] = NDPHP_LANG_MOD_REGISTER_USER_REGISTRATION;
		$data['view']['description'] = NDPHP_LANG_MOD_REGISTER_USER_REGISTRATION;
		$data['view']['countries'] = $query;

		$this->load->view('themes/' . $this->_theme . '/' . 'register/recover_password', $data);
	}

	public function recover_password() {
		if ($this->register_with_recaptcha == '1') {
			$res = recaptcha_check_answer($this->recaptcha_private_key, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

			if (!$res->is_valid) {
				header('HTTP/1.1 403 Forbidden');
				die(NDPHP_LANG_MOD_INVALID_RECAPTCHA_VALUE);
			}
		}

		if (!isset($_POST['email'])) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MISSING_EMAIL);
		} else {
			$email = $_POST['email'];
		}

		if (!isset($_POST['phone'])) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MISSING_PHONE);
		} else {
			$phone = $_POST['phone'];
		}

		if (!isset($_POST['first_name'])) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MISSING_FIRST_NAME);
		} else {
			$first_name = $_POST['first_name'];
		}

		if (!isset($_POST['last_name'])) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MISSING_LAST_NAME);
		} else {
			$last_name = $_POST['last_name'];
		}

		if (!isset($_POST['countries_id']) || !$_POST['countries_id'] || ($_POST['countries_id'] == 242)) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_MISSING_VALID_COUNTRY);
		} else {
			$countries_id = $_POST['countries_id'];
		}

		$this->db->select('id,username');
		$this->db->from('users');
		$this->db->where('email', $email);
		$this->db->where('phone', $phone);
		$this->db->where('first_name', $first_name);
		$this->db->where('last_name', $last_name);
		$this->db->where('countries_id', $countries_id);

		$query = $this->db->get();

		if (!$query->num_rows()) {
			error_log('recover_password(): No data match.');
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_REGISTER_NO_DATA_MATCH);
		}

		$rawdata = $query->row_array();

		if ($rawdata['id'] == 1) {
			error_log('recover_password(): No data match.');
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_REGISTER_NO_DATA_MATCH);
		}

		$plain_password = $this->rand_string(24);
		$userdata['password'] = password_hash($plain_password, PASSWORD_BCRYPT, array('cost' => 10));

		$this->db->where('id', $rawdata['id']);
		$this->db->update('users', $userdata);

		$this->send_credentials_email($email, $first_name, $last_name, $rawdata['username'], $plain_password);

		echo('<br />' . NDPHP_LANG_MOD_REGISTER_EMAIL_RECOVER_INFO . '<br />');
		echo('<br /><br /><center><a href="' . base_url() . '/index.php/login" class="register_button_link">' . NDPHP_LANG_MOD_LOGIN_LOGIN . '</a></center>');
	}

	public function confirm_sms_form($users_id, $ajax = 0) {
		$users_id = explode('.', $this->encrypt->decode($this->ndphp->safe_b64decode(urldecode($users_id))));
		$users_id = $users_id[0];

		$this->db->select('phone_confirmed');
		$this->db->from('users');
		$this->db->where('id', $users_id);

		$query = $this->db->get();

		if (!$query->num_rows()) {
			error_log('register.php: confirm_sms_form(): Invalid users_id: ' . $users_id);
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_USER_ID);
		}

		$row = $query->row_array();

		if (intval($row['phone_confirmed']) == 1) {
			exit(NDPHP_LANG_MOD_REGISTER_PHONE_CONFIRMED);
		}

		$data = array();

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['theme'] = $this->_get_theme();

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['title'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_SMS_TOKEN;
		$data['view']['description'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_SMS_TOKEN;
		$data['view']['users_id'] = $users_id;

		if ($ajax) {
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_sms_ajax', $data);
		} else {
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_sms', $data);
		}
	}

	public function confirm_sms_token() {
		$users_id = explode('.', $this->encrypt->decode($this->ndphp->safe_b64decode(urldecode($_POST['users_id']))));
		$users_id = $users_id[0];

		$smstoken = $_POST['smstoken'];

		$this->db->select('confirm_phone_token,phone_confirmed');
		$this->db->from('users');
		$this->db->where('id', $users_id);

		$query = $this->db->get();

		if (!$query->num_rows()) {
			error_log('register.php: confirm_sms_token(): Invalid users_id: ' . $users_id);
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_USER_ID);
		}

		$row = $query->row_array();

		if (intval($row['phone_confirmed']) == 1) {
			exit(NDPHP_LANG_MOD_REGISTER_PHONE_CONFIRMED);
		}

		if ($row['confirm_phone_token'] != $smstoken) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_INVALID_SMS_TOKEN);
		}

		$userdata['phone_confirmed'] = 1;

		$this->db->trans_begin();

		$this->db->where('id', $users_id);
		$this->db->update('users', $userdata);

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			error_log('register.php: confirm_sms_token(): Failed to update phone_confirmed on User ID: ' . $users_id);
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_FAILED_TRANSACTION . ' #1. ' . NDPHP_LANG_MOD_ATTN_CONTACT_SUPPORT);
		} else {
			$this->db->trans_commit();
		}

		$res = $this->user_try_unlock($users_id);

		echo(NDPHP_LANG_MOD_SUCCESS_PHONE_VERIFICATION . '<br />');

		if ($res === true) {
			$this->user_active_process($users_id);

			echo('<br />' . NDPHP_LANG_MOD_REGISTER_USER_ACCT_IS_NOW . ' <span style="font-weight: bold">' . NDPHP_LANG_MOD_WORD_ACTIVE_F . '</span>.<br />');
			echo('<br /><br /><center><a href="' . base_url() . '/index.php/login" class="register_button_link">' . NDPHP_LANG_MOD_LOGIN_LOGIN . '</a></center>');
		} else {
			echo('<br />' . NDPHP_LANG_MOD_REGISTER_CHECK_EMAIL_INBOX . '<br />');
		}
	}

	public function confirm_email_hash($users_id, $hash) {
		$users_id = explode('.', $this->encrypt->decode($this->ndphp->safe_b64decode(urldecode($users_id))));
		$users_id = $users_id[0];

		$data['config'] = array();
		$data['config']['author'] = $this->_author;
		$data['config']['charset'] = $this->_charset;
		$data['config']['theme'] = $this->_get_theme();

		$data['project'] = array();
		$data['project']['author'] = $this->_author;
		$data['project']['name'] = $this->_project_name;
		$data['project']['tagline'] = $this->_tagline;
		$data['project']['description'] = $this->_description;

		$data['view'] = array();
		$data['view']['title'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_EMAIL_STATUS;
		$data['view']['description'] = NDPHP_LANG_MOD_REGISTER_CONFIRM_EMAIL_STATUS;

		$this->db->select('confirm_email_hash,email_confirmed');
		$this->db->from('users');
		$this->db->where('id', $users_id);

		$query = $this->db->get();

		if (!$query->num_rows()) {
			error_log('register.php: confirm_email_hash(): Invalid users_id: ' . $users_id);
			header('HTTP/1.1 403 Forbidden');
			$data['view']['message'] = NDPHP_LANG_MOD_INVALID_USER_ID . '<br />';
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
			return;
		}

		$row = $query->row_array();

		if (intval($row['email_confirmed']) == 1) {
			$data['view']['message'] = NDPHP_LANG_MOD_REGISTER_EMAIL_CONFIRMED . '<br />';
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
			return;
		}

		if (strcmp($row['confirm_email_hash'], $hash)) {
			header('HTTP/1.1 403 Forbidden');
			$data['view']['message'] = NDPHP_LANG_MOD_INVALID_EMAIL_HASH . '<br />';
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
			return;
		}

		$userdata['email_confirmed'] = 1;

		$this->db->trans_begin();

		$this->db->where('id', $users_id);
		$this->db->update('users', $userdata);

		
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			error_log('register.php: confirm_email_hash(): Failed to update email_confirmed on User ID: ' . $users_id);
			header('HTTP/1.1 500 Internal Server Error');
			$data['view']['message'] = NDPHP_LANG_MOD_FAILED_TRANSACTION . ' #1. ' . NDPHP_LANG_MOD_ATTN_CONTACT_SUPPORT;
			$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
			return;
		} else {
			$this->db->trans_commit();
		}

		$res = $this->user_try_unlock($users_id);


		/* TODO: FIXME: $data['view']['message'] contains HTML generated in this controller... Only the view should contain HTML */


		$data['view']['message'] = NDPHP_LANG_MOD_SUCCESS_EMAIL_VERIFICATION . '<br />';

		if ($res === true) {
			$this->user_active_process($users_id);

			$data['view']['message'] .= '<br />' . NDPHP_LANG_MOD_REGISTER_USER_ACCT_IS_NOW . ' <span style="font-weight: bold">' . NDPHP_LANG_MOD_WORD_ACTIVE_F . '</span>.<br />';
			$data['view']['message'] .= '<br /><br /><center><a href="' . base_url() . '/index.php/login" class="register_button_link">' . NDPHP_LANG_MOD_LOGIN_LOGIN . '</a></center>';
		} else {
			if ($this->register_confirm_phone == '1') {
				$data['view']['message'] .= '<br />' . NDPHP_LANG_MOD_REGISTER_CHECK_MOBILE_EMAIL . '<br />';
			} else {
				$data['view']['message'] .= '<br />' . NDPHP_LANG_MOD_REGISTER_CHECK_EMAIL_INBOX . '<br />';
			}
		}

		$this->load->view('themes/' . $this->_theme . '/' . 'register/confirm_email_status', $data);
	}

	private function user_active_process($users_id) {
		/* Update users_id and apikey on users table */
		$userdata['users_id'] = $users_id;
		$userdata['apikey'] = openssl_digest(openssl_random_pseudo_bytes(256), 'sha1');
		$userdata['acct_last_reset'] = date('Y-m-d H:i:s');

		$this->db->trans_begin();

		$this->db->where('id', $users_id);
		$this->db->update('users', $userdata);

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			error_log('register.php: user_active_process(): Unable to update users_id on User ID: ' . $users_id);
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_UNABLE_UPDATE_TABLE_USERS . '<br />');
		} else {
			$this->db->trans_commit();
		}

		/* Setup roles */
		$roledata['users_id'] = $users_id;
		$roledata['roles_id'] = $this->roles_regular_id;

		$this->db->trans_begin();

		$this->db->insert('rel_users_roles', $roledata);

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			error_log('register.php: user_active_process(): Failed to update rel_users_roles on User ID: ' . $users_id);
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_FAILED_UPDATE_USER_ROLES);
		} else {
			$this->db->trans_commit();
		}
	}

	private function user_try_unlock($users_id) {
		$this->db->select('email_confirmed,phone_confirmed');
		$this->db->from('users');
		$this->db->where('id', $users_id);

		$query = $this->db->get();

		if (!$query->num_rows())
			return false;

		$row = $query->row_array();

		if (($this->register_confirm_email == 1) && ($this->register_confirm_phone == 1)) {
			if (($row['email_confirmed'] != 1) || ($row['phone_confirmed'] != 1))
				return false;
		} else if ($this->register_confirm_email == 1) {
			if ($row['email_confirmed'] != 1)
				return false;
		} else if ($this->register_confirm_phone == 1) {
			if ($row['phone_confirmed'] != 1)
				return false;
		}

		$userdata['active'] = 1;
		$userdata['locked'] = 0;
		$userdata['date_confirmed'] = date('Y-m-d H:i:s');
		$userdata['expire'] = (date('Y') + 1) . '-' . date('m-d H:i:s'); // 1 year active before re-confirmation

		$this->db->trans_begin();

		$this->db->where('id', $users_id);
		$this->db->update('users', $userdata);

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			error_log('register.php: user_try_unlock(): Failed to update user lock on User ID: ' . $users_id);
			header('HTTP/1.1 500 Internal Server Error');
			die(NDPHP_LANG_MOD_FAILED_TRANSACTION . ' #1');
		} else {
			$this->db->trans_commit();
		}

		return true;
	}
}

