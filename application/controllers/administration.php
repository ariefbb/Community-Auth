<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Administration Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Administration extends MY_Controller {

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Force encrypted connection
		$this->force_ssl();
	}

	// --------------------------------------------------------------

	/**
	 * Create a user 
	 */
	public function create_user()
	{
		// Make sure an Admin or Manager is logged in. (showing how to log in by group)
		if( $this->require_group('Employees') )
		{
			// Load resources
			$this->load->library('csrf');

			$view_data = array();

			// Check if a valid form submission has been made
			if( $this->csrf->token_match )
			{
				// Create the user
				$this->load->model('user_model');
				$this->user_model->create_user();
			}

			$data = array(
				'content' => $this->load->view( 'administration/create_user', $view_data, TRUE ),

				// Load the show password script
				'javascripts' => array(
					'js/jquery.passwordToggle-1.1.js',
					'js/jquery.char-limiter-3.0.0.js',
					'js/default-char-limiters.js'
				),

				// Use the show password script
				'extra_head' => '
					<script>
						$(document).ready(function(){
							$("#show-password").passwordToggle({target:"#user_pass"});
						});
					</script>
				'
			);

			$this->load->view( $this->template, $data );

		}

	}

	// --------------------------------------------------------------

	/**
	 * Manage users 
	 *
	 * @param  int  the pagination page number
	 */
	public function manage_users( $page = 1 )
	{
		// Make sure an admin or manager is logged in
		if( $this->require_role('Admin,Manager') )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->model('user_model');
			$this->load->library('pagination');

			// If an ajax request
			if( $this->input->is_ajax_request() )
			{
				// If form token matches
				if( $this->csrf->token_match )
				{
					// Get table and pagination content
					$this->_manage_users_table_content( $page, 'json' );
				}
				else
				{
					// Send error back instead of table and pagination content
					$view_data = array(
						'test' => 'error',
						'message' => 'No Token Match - Please Reload Page'
					);

					// Send response
					echo json_encode( $view_data );
				}
			}

			// If the initial page load, or a standard request
			else
			{
				$view_data = $this->_manage_users_table_content( $page );

				$data = array(
					'javascripts' => array(
						'js/administration/manage-users.js'
					),
					'content' => $this->load->view('administration/manage_users', $view_data, TRUE)
				);

				$this->load->view( $this->template, $data );
			}
		}

	}

	// --------------------------------------------------------------

	/**
	 * Create user management table content
	 * 
	 * @param  int     the pagination page number
	 * @param  mixed   NULL if a standard page request or json if an ajax request
	 */
	private function _manage_users_table_content( $page = 1, $format = NULL )
	{
		// The pagination class doesn't allow for multiple config files, so we load configuration the old fashion way
		$this->config->load( 'pagination/administration/manage_users_pagination' );
		$config = config_item('manage_users_pagination_settings');

		// If search_in and search_for are not empty, this is a search
		$search_in = $this->input->post('search_in', TRUE );
		$search_for = $this->input->post('search_for', TRUE );

		// Make sure that search_in is one of the allowable values in the config
		if( $search_in !== FALSE && ! in_array( $search_in, array_keys( config_item('manage_users_search_options') ) ) )
		{
			$search_in = FALSE;
		}

		// Set the query params for both count and data queries
		$query_params = array(
			'user_level' => $this->auth_level,
			'search_in'  => $search_in,
			'search_for' => $search_for,
			'limit'      => $config['per_page'],
			'page'       => (int) $page
		);

		// Get the total rows that match the requested set of users, or all by default
		$config['total_rows'] = $this->user_model->manage_user_records_data( $query_params, TRUE );

		// Initialize pagination and create links
		$this->pagination->initialize( $config );
		$view_data['pagination_links'] = $this->pagination->create_links();

		// Get the actual user data that matches the requested set of users
		$view_data['users_data'] = $this->user_model->manage_user_records_data();

		// Insert the user data into table rows ( a nested view )
		$view_data['table_content'] = $this->load->view('administration/manage_users_table_content', $view_data, TRUE );

		// If an ajax request
		if( $format == 'json' )
		{
			// Remove the raw user data from the ajax response
			unset( $view_data['users_data'] );

			// Add success confirmation, token, and CI csrf token to the response
			$view_data['test'] = 'success';
			$view_data['token'] = $this->csrf->token;
			$view_data['ci_csrf_token'] = $this->security->get_csrf_hash();

			// Send the ajax response
			echo json_encode( $view_data );
		}

		// If the initial page load, or standard request
		else
		{
			return $view_data;
		}
	}

	// --------------------------------------------------------------

	/**
	 * Delete a user
	 *
	 * @param  int  the user_id of the user to delete.
	 * @param  int  the pagination page number to redirect back to.
	 */
	public function delete_user( $user_to_delete = FALSE, $page = FALSE )
	{
		// Make sure admin or manager is logged in
		if( $this->require_role('Admin,Manager') )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->model('user_model');

			// Must not be a user trying to delete themeselves
			if( is_numeric( $user_to_delete ) && $user_to_delete != $this->auth_user_id )
			{
				// If an ajax request
				if( $this->input->is_ajax_request() )
				{
					// Must pass CSRF token match and delete_user must return TRUE
					if( 
						$this->csrf->token_match && 
						$this->user_model->delete_user( $user_to_delete, $this->auth_level )
					)
					{
						// Send success message back
						$response = array(
							'test'          => 'success',
							'token'         => $this->csrf->token,
							'ci_csrf_token' => $this->security->get_csrf_hash()
						);
					}
					else
					{
						// CSRF token mismatch or delete_user was FALSE
						$response = array(
							'test'    => 'error',
							'message' => 'No Token Match - Please Reload Page'
						);
					}

					echo json_encode( $response );
				}

				// If standard request
				else
				{
					$test = $this->user_model->delete_user( $user_to_delete, $this->auth_level );

					$page = ( $page ) ? '/' . $page : '';

					header("Location: " . secure_site_url( 'administration/manage_users' . $page ) );

					exit;
				}
			}
		}
	}

	// --------------------------------------------------------------

	/**
	 * Update a user
	 */
	public function update_user( $the_user = 0 )
	{
		// Make sure an admin or manager is logged in
		if( $this->require_role('Admin,Manager') )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->library('encrypt');
			$this->load->model('user_model');
			$this->config->load('uploads_manager');

			// Get the user level of the user to be updated
			$users_level_query = $this->user_model->view_user_record( $the_user, 'u.`user_level`' );

			/**
			 * If the user to be updated has a user level greater than or equal
			 * to the logged in user, then we don't want to show the user's details
			 */
			if( $users_level_query->user_level >= $this->auth_level )
			{
				die();
			}

			/*
			 * Check if form posted
			 */
			if( $this->csrf->token_match )
			{
				// Update the user
				$this->user_model->update_user( $the_user, 'update_user' );
			}

			// If this is an update, just show that user
			$user_row = $this->user_model->view_user_record( $the_user );

			// Decrypt any sensitive data for display
			$user_row->license_number = $this->encrypt->decode( $user_row->license_number );

			// Send user data to view
			$view_data['user_data'] = $user_row;

			$data = array(
				'content' => $this->load->view( 'administration/update_user', $view_data, TRUE ),

				// Load the show password script
				'javascripts' => array(
					'js/jquery.passwordToggle-1.1.js',
					'js/jquery.char-limiter-3.0.0.js',
					'js/default-char-limiters.js'
				),

				// Use the show password script
				'extra_head' => '
					<script>
						$(document).ready(function(){
							$("#show-password").passwordToggle({target:"#user_pass"});
							$("#show-password").passwordToggle({target:"#user_pass_confirm"});
						});
					</script>
				'
			);

			$this->load->view( $this->template, $data );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Deny access and manage denied access to an IP, IP block, etc.
	 *
	 * Here you can deny access or manage the deny list in 
	 * your local Apache configuration file. Please note that we've 
	 * all had experiences where a little mistake in one of these
	 * files can bring down the whole website. For this reason, 
	 * access is restricted to Admin only.
	 */
	public function deny_access()
	{
		// Make sure admin is logged in
		if( $this->require_role('Admin') )
		{
			if( config_item('deny_access') > 0 )
			{
				// Load resources
				$this->load->library('csrf');

				// If POST, do delete or addition of IP
				if( $this->csrf->token_match )
				{
					$this->auth_model->process_denial();
				}

				// Get the current deny list
				$view_data['deny_list'] = $this->auth_model->get_deny_list();
			}

			$data = array(
				'content' => $this->load->view( 'administration/deny_access', ( isset( $view_data ) ) ? $view_data : '', TRUE ),
				'javascripts' => array(
					'js/jquery.char-limiter-3.0.0.js',
					'js/default-char-limiters.js'
				)
			);

			$this->load->view( $this->template, $data );
		}
	}

	// --------------------------------------------------------------
}

/* End of file administration.php */
/* Location: /application/controllers/administration.php */