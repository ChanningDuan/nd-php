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

/**
 * Notes:
 *
 * - Field users.id and users.users_id must always match on users table for each row.
 *
 */

class Users extends ND_Controller {
	/* Constructor */
	public function __construct($session_enable = true, $json_replies = false) {
		parent::__construct($session_enable, $json_replies);

		$this->_viewhname = get_class();
		$this->_name = strtolower($this->_viewhname);
		$this->_hook_construct();

		/* Include any setup procedures from ide builder. */
		include('lib/ide_setup.php');

		/* TODO: FIXME: If sharding is enabled, we must load the main database here ('default') and then
		 * grant that all changes are also replicated to the user shard.
		 */
	}

	/** Hooks **/
	protected function _hook_insert_pre(&$POST, &$fields) {
		$features = $this->_get_features();

		/* Check if we're under multi or single user mode */
		if (!$features['multi_user']) {
			/* If we're under single user mode, user registration is not available */
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_DISABLED_MULTI_USER);
		}

		/* Generate user's private key for encryption
		 *
		 * This key will be a pseudo random string with 256 by of length.
		 * It'll be encrypted with the user's password.
		 * Each time the user logs in, the private key is deciphered with the plain password used for authentication
		 * and the decrypted key will be stored as a session variable.
		 *
		 */
		$POST['privenckey'] = $this->encrypt->encrypt(openssl_random_pseudo_bytes(256), $POST['password'], false);

		/* Convert password to hash */
		$POST['password'] = password_hash($POST['password'], PASSWORD_BCRYPT, array('cost' => 10));
	}

	protected function _hook_insert_post(&$id, &$POST, &$fields, $hook_pre_return) {
		/* Grant that users_id is set */
		$this->db->trans_begin();

		$this->db->where('users.id', $id);
		$this->db->update('users', array('users_id' => $id));

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();

			/* Try to delete the newly inserted user */
			$this->db->delete('users', array('id' => $id));

			header("HTTP/1.0 500 Internal Server Error");
			die(NDPHP_LANG_MOD_FAILED_UPDATE_USER_DATA);
		} else {
			$this->db->trans_commit();
		}
	}

	protected function _hook_update_pre(&$id, &$POST, &$fields) {
		/* FIXME: Ignore any attempt to remove ROLE_ADMIN from $id == 1 */

		/* If password was changed ... */
		$this->db->select('password,privenckey');
		$this->db->from($this->_name);
		$this->db->where('id', $id);
		$query = $this->db->get();
		$row = $query->row_array();

		if ($row['password'] != $POST['password']) {
			/* WARNING: If we're updating the password via REST API, we need to grant that the call passed the plain password
			 * for authentication (in the '_password' JSON request field) in addition to the API KEY. If not, the privenckey
			 * session variable is NULL and thus we cannot change the user password (or he will never access the private encrypted
			 * data again).
			 */

			if (strlen(base64_decode($this->session->userdata('privenckey'))) != 256) {
				/* As stated, if the deciphered private encryption key doesn't seem right, we won't allow the password
				 * to be changed.
				 */
				header('HTTP/1.1 401 Unauthorized');
				die(NDPHP_LANG_MOD_ATTN_INSUFFICIENT_CREDS);
			}

			/* Re-encrypt the user private encryption key with the new password */
			$POST['privenckey'] = $this->encrypt->encrypt(base64_decode($this->session->userdata('privenckey')), $POST['password'], false);

			/* hash new password */
			$POST['password'] = password_hash($POST['password'], PASSWORD_BCRYPT, array('cost' => 10));
		}

		/* Grant that users_id is set */
		$POST['users_id'] = $id;
	}

	protected function _hook_update_post(&$id, &$POST, &$fields, $hook_pre_return) {
		/* Always update user session data after any user changes are performed */

		/* Query the database */
		$this->db->select('users.id AS user_id,users.username AS username,users.email AS email,users._file_photo AS photo,rel_users_roles.roles_id AS roles_id,timezones.timezone AS timezone,users.privenckey');
		$this->db->from('users');
		$this->db->join('rel_users_roles', 'rel_users_roles.users_id = users.id', 'left');
		$this->db->join('timezones', 'users.timezones_id = timezones.id', 'left');
		$this->db->where('users.id', $id);

		$query = $this->db->get();

		if (!$query->num_rows()) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_UNABLE_UPDATE_SESSION_DATA . ' ' . NDPHP_LANG_MOD_ATTN_LOGOUT_LOGIN);
		}

		/* Only update session data if the user being updated is the user who's performing the update */
		if ($this->_session_data['user_id'] == $id) {
			$user_roles = array();

			foreach ($query->result_array() as $row) {
				array_push($user_roles, $row['roles_id']);
			}
			
			/* Update user session data */
			$this->_session_data['username'] = $row['username'];
			$this->_session_data['photo'] = $row['photo'] ? (base_url() . 'index.php/files/access/users/' . $id . '/_file_photo/' . $row['photo']) : NULL;
			$this->_session_data['email'] = $row['email'];
			$this->_session_data['timezone'] = $row['timezone'];
			$this->_session_data['privenckey'] = base64_encode($row['privenckey']);
			/* FIXME: Missing database variable? */
			$this->_session_data['roles'] = $user_roles;

			$this->session->set_userdata($this->_session_data);
		}
	}

	protected function _hook_delete_pre(&$id, &$POST, &$fields) {
		$hook_pre_return = NULL;
		
		if ($id == 1) {
			header('HTTP/1.1 403 Forbidden');
			die(NDPHP_LANG_MOD_CANNOT_DELETE_ADMIN_USER);
		}

		return $hook_pre_return;
	}

	protected function _hook_view_generic_leave(&$data, &$id, &$export, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_remove_generic_leave(&$data, &$id, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_edit_generic_leave(&$data, &$id, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_create_generic_leave(&$data, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_export_leave(&$data, &$export_query, &$type, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_list_generic_leave(&$data, &$field, &$order, &$page, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_result_generic_leave(&$data, &$type, &$result_query, &$order_field, &$order_type, &$page, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_search_generic_leave(&$data, &$advanced, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);
	}

	protected function _hook_groups_generic_leave(&$data, $hook_enter_return) {
		/* Unset fields based on disabled features */
		$this->_feature_filter_data_fields($data);

		/* Initialize a new groups array */
		$groups = array();

		/* Filter groups belonging to filtered fields */
		foreach ($data['view']['groups'] as $group) {
			if (!array_key_exists($group['table_field'], $data['view']['fields']))
				continue;

			array_push($groups, $group);
		}

		/* Update groups in view data */
		$data['view']['groups'] = $groups;
	}

	/** Other overloads **/
	/* Hidden fields per view.
	 *
	 * Note that for relationship fields, the field name used here must be the one
	 * corresponding to the foreign table field.
	 * 
	 */
	protected $_hide_fields_create = array('id');
	protected $_hide_fields_edit = array('id');
	protected $_hide_fields_view = array('password');
	protected $_hide_fields_remove = array('password');
	protected $_hide_fields_list = array('password', 'phone', 'address_line1', 'address_line2', 'city', 'postcode', 'vat', 'apikey', 'confirm_email_hash', 'confirm_phone_token', 'phone_confirmed', 'date_confirmed', 'registered', 'email_confirmed', 'allow_negative', 'expire', 'subscription_change_date', 'subscription_renew_date', 'company', 'first_name', 'last_name', 'acct_last_reset', 'acct_rest_list', 'acct_rest_result', 'acct_rest_view', 'acct_rest_delete', 'acct_rest_update', 'acct_rest_insert');
	protected $_hide_fields_result = array('password', 'phone', 'address_line1', 'address_line2', 'city', 'postcode', 'vat', 'apikey', 'confirm_email_hash', 'confirm_phone_token', 'phone_confirmed', 'date_confirmed', 'registered', 'email_confirmed', 'allow_negative', 'expire', 'subscription_change_date', 'subscription_renew_date', 'company', 'first_name', 'last_name', 'acct_last_reset', 'acct_rest_list', 'acct_rest_result', 'acct_rest_view', 'acct_rest_delete', 'acct_rest_update', 'acct_rest_insert');
	protected $_hide_fields_search = array('password'); // Include fields searched on searchbar (basic)
	protected $_hide_fields_export = array('password');

	protected $_submenu_body_links_view = array(
		/* array('Description', $sec_perm, method, 'image/path/img.png', 'ajax' / 'export' / 'method' / 'modal' / 'raw', with id?, access key) */
		array(NDPHP_LANG_MOD_OP_CREATE,			'C', 'create',		NULL, 'ajax',   false,	NDPHP_LANG_MOD_OP_ACCESS_KEY_CREATE	),
		array(NDPHP_LANG_MOD_OP_REMOVE,			'D', 'remove',		NULL, 'ajax',   true,	NDPHP_LANG_MOD_OP_ACCESS_KEY_REMOVE	),
		array(NDPHP_LANG_MOD_OP_EDIT,			'U', 'edit',		NULL, 'ajax',   true,	NDPHP_LANG_MOD_OP_ACCESS_KEY_EDIT	),
		array(NDPHP_LANG_MOD_OP_LIST,			'R', 'list',		NULL, 'ajax',   false,	NDPHP_LANG_MOD_OP_ACCESS_KEY_LIST	),
		array(NDPHP_LANG_MOD_OP_GROUPS,			'R', 'groups',		NULL, 'ajax',	false,	NDPHP_LANG_MOD_OP_ACCESS_KEY_GROUPS	),
		array(NDPHP_LANG_MOD_OP_SEARCH,			'R', 'search',		NULL, 'ajax',   false,	NDPHP_LANG_MOD_OP_ACCESS_KEY_SEARCH	),
		array(NDPHP_LANG_MOD_OP_EXPORT_PDF,		'R', 'pdf',			NULL, 'export', true,	NULL 								),
		array(NDPHP_LANG_MOD_OP_LOGOUT,			'R', 'logout',		NULL, 'method', false,	NULL 								)
	);

	/* Aliases for the current table field names */
	protected $_table_field_aliases = array(
		'username' => NDPHP_LANG_MOD_COMMON_USERNAME,
		'password' => NDPHP_LANG_MOD_COMMON_PASSWORD,
		'_file_photo' => NDPHP_LANG_MOD_COMMON_PHOTO,
		'email' => NDPHP_LANG_MOD_COMMON_EMAIL,
		'phone' => NDPHP_LANG_MOD_COMMON_PHONE,
		'active' => NDPHP_LANG_MOD_COMMON_ACTIVE,
		'locked' => NDPHP_LANG_MOD_COMMON_LOCKED,
		'_separator_subscription' => NDPHP_LANG_MOD_SEP_USER_SUBSCRIPTION,
		'subscription_change_date' => NDPHP_LANG_MOD_COMMON_SUBSCR_CHANGE_DATE,
		'subscription_renew_date' => NDPHP_LANG_MOD_COMMON_SUBSCR_RENEW_DATE,
		'_separator_personal' => NDPHP_LANG_MOD_SEP_USER_PERSONAL,
		'first_name' => NDPHP_LANG_MOD_COMMON_FIRST_NAME,
		'last_name' => NDPHP_LANG_MOD_COMMON_LAST_NAME,
		'company' => NDPHP_LANG_MOD_COMMON_COMPANY_NAME,
		'address_line1' => NDPHP_LANG_MOD_COMMON_ADDR_LINE1,
		'address_line2' => NDPHP_LANG_MOD_COMMON_ADDR_LINE2,
		'city' => NDPHP_LANG_MOD_COMMON_CITY,
		'postcode' => NDPHP_LANG_MOD_COMMON_POSTCODE,
		'vat' => NDPHP_LANG_MOD_COMMON_VAT_NUMBER,
		'_separator_register' => NDPHP_LANG_MOD_SEP_USER_REGISTER,
		'expire' => NDPHP_LANG_MOD_COMMON_EXPIRE,
		'registered' => NDPHP_LANG_MOD_COMMON_REGISTERED,
		'last_login' => NDPHP_LANG_MOD_COMMON_LAST_LOGIN,
		'confirm_email_hash' => NDPHP_LANG_MOD_COMMON_CONFIRM_EMAIL_HASH,
		'confirm_phone_token' => NDPHP_LANG_MOD_COMMON_CONFIRM_PHONE_TOKEN,
		'email_confirmed' => NDPHP_LANG_MOD_COMMON_EMAIL_CONFIRMED,
		'phone_confirmed' => NDPHP_LANG_MOD_COMMON_PHONE_CONFIRMED,
		'date_confirmed' => NDPHP_LANG_MOD_COMMON_DATE_CONFIRMED,
		'_separator_credit' => NDPHP_LANG_MOD_SEP_USER_CREDIT,
		'credit' => NDPHP_LANG_MOD_COMMON_CREDIT,
		'allow_negative' => NDPHP_LANG_MOD_COMMON_ALLOW_NEG_CREDIT,
		'_separator_api' => NDPHP_LANG_MOD_SEP_USER_API,
		'apikey' => NDPHP_LANG_MOD_COMMON_API_KEY,
		'_separator_accounting' => NDPHP_LANG_MOD_SEP_USER_ACCOUNTING,
		'acct_last_reset' => NDPHP_LANG_MOD_COMMON_ACCT_LAST_RESET,
		'acct_rest_list' => NDPHP_LANG_MOD_COMMON_ACCT_REST_LIST_CNTR,
		'acct_rest_result' => NDPHP_LANG_MOD_COMMON_ACCT_REST_RESULT_CNTR,
		'acct_rest_view' => NDPHP_LANG_MOD_COMMON_ACCT_REST_VIEW_CNTR,
		'acct_rest_delete' => NDPHP_LANG_MOD_COMMON_ACCT_REST_DELETE_CNTR,
		'acct_rest_update' => NDPHP_LANG_MOD_COMMON_ACCT_REST_UPDATE_CNTR,
		'acct_rest_insert' => NDPHP_LANG_MOD_COMMON_ACCT_REST_INSERT_CNTR,
		'_separator_sharding' => NDPHP_LANG_MOD_COMMON_SHARDING
	);

	protected $_rel_table_fields_config = array(
		'timezones' => array(NDPHP_LANG_MOD_COMMON_TIMEZONE, NULL, array(1), array('id', 'asc')),
		'subscription_types' => array(NDPHP_LANG_MOD_COMMON_SUBSCRIPTION, NULL, array(1), array('id', 'asc')),
		'countries' => array(NDPHP_LANG_MOD_COMMON_COUNTRY, NULL, array(1), array('id', 'asc')),
		'dbms' => array(NDPHP_LANG_MOD_COMMON_DATABASE_ALIAS, NULL, array(1), array('id', 'asc')),
		'roles' => array(NDPHP_LANG_MOD_SEP_USER_ROLES, NULL, array(1), array('id', 'asc'))
	);

	/** Custom functions **/
	private function _feature_filter_data_fields(&$data) {
		/* Unset fields based on disabled features */
		if (!$data['config']['features']['user_credit_control']) {
			unset($data['view']['fields']['_separator_credit']);
			unset($data['view']['fields']['credit']);
			unset($data['view']['fields']['allow_negative']);
		}

		if (!$data['config']['features']['user_subscription_types']) {
			unset($data['view']['fields']['_separator_subscription']);
			unset($data['view']['fields']['subscription_types_id']);
			unset($data['view']['fields']['subscription_change_date']);
			unset($data['view']['fields']['subscription_renew_date']);
		}

		if (!$data['config']['features']['system_sharding']) {
			unset($data['view']['fields']['_separator_sharding']);
			unset($data['view']['fields']['dbms_id']);
		}
	}

	public function user_credit_get() {
		$this->db->select('credit');
		$this->db->from('users');
		$this->db->where('id', $this->session->userdata('user_id'));
		$query = $this->db->get();
		$rawdata = $query->row_array();

		echo(round($rawdata['credit'], 2));
	}

	public function user_subscription_get() {
		$this->db->select('subscription_types.subscription_type AS subscription');
		$this->db->from('users');
		$this->db->join('subscription_types', 'subscription_types.id = users.subscription_types_id', 'left');
		$this->db->where('users.id', $this->session->userdata('user_id'));
		$query = $this->db->get();
		$rawdata = $query->row_array();

		echo($rawdata['subscription']);
	}

	public function logout() {
		redirect('/login/logout');
	}
}
