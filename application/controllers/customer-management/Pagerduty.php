<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pagerduty extends CI_Controller 
{
	public function __construct()
	{
		parent::__construct();
		
		if (!$this->tank_auth->is_logged_in()) 
		{	
			if ($this->input->is_ajax_request()) 
			{
				redirect('/auth/ajax_logged_out_response');
			} 
			else 
			{
				redirect('/auth/login');
			}
		}
		$this->utility->restricted_access();

		$this->load->model('account/account_model', 'account');
		$this->load->library('pagerduty_api');
	}

	function _remap($method, $args)
	{ 
		if (method_exists($this, $method))
		{
			$this->$method($args);
		}
		else
		{
			$this->index($method, $args);
		}
	}

	public function index($method, $args = array())
	{
		$asset = client_redis_info_by_code();

		# Page Data	
		$nav['client_name'] 		= $asset['client'];
		$nav['client_code'] 		= $asset['code'];
		
		$data['client_code'] 		= $asset['code'];
		$data['sub_navigation']     = $this->load->view('customer-management/navigation', $nav, TRUE);	
		$data['pagerduty_info'] 	= $this->pagerduty_api->redis_info($asset['seed_name']);
		$data['show_activation'] 	= FALSE;
		$data['api_tested'] 		= FALSE;
		$data['request_was_sent']   = FALSE;
		$data['api_enabled'] 		= FALSE;
		$data['action']             = 'create';
		
		if (!is_null($data['pagerduty_info']))
		{
			$data['action'] = 'modify';
			
			if (intval($data['pagerduty_info']['tested']))
			{
				$data['show_activation']    = TRUE;
				$data['api_tested'] 		= TRUE;
				
				if (intval($data['pagerduty_info']['request_sent']))
				{
					$data['request_was_sent'] = TRUE;
				}
				
				if (intval($data['pagerduty_info']['enabled']))
				{
					$data['api_enabled'] = TRUE;
				}
			}
		}

		# Page Views
		$this->load->view('assets/header');	
		$this->load->view('customer-management/pagerduty/start', $data);	
		$this->load->view('assets/footer');
	}

	public function create()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('routing_key', 'Routing Key', 'trim|required|exact_length[32]|ctype_xdigit');
			$this->form_validation->set_message('ctype_xdigit', 'The {field} field must only contain hexadecimal digits [0-9a-f].');

			if ($this->form_validation->run()) 
			{
				$redis_data = array(
					'routing_key' => $this->input->post('routing_key')
				);

				if ($this->pagerduty_api->create_pagerduty_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[PagerDuty API Created] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);
					
					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>PagerDuty API settings were successfully created.</p>');

					redirect('/customer-management/pagerduty/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}
		
		# Page Data
		$data['client_code'] = $asset['code'];
		
		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/pagerduty/create', $data);
		$this->load->view('assets/footer');
	}

	public function modify()
	{
		$asset = client_redis_info_by_code();

		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('routing_key', 'Routing Key', 'trim|required|exact_length[32]|ctype_xdigit');
			$this->form_validation->set_message('ctype_xdigit', 'The {field} field must only contain hexadecimal digits [0-9a-f].');

			if ($this->form_validation->run())
			{
				$redis_data = array(
					'routing_key' => $this->input->post('routing_key')
				);

				if ($this->pagerduty_api->create_pagerduty_redis_key($asset['seed_name'], $redis_data))
				{
					# Write To Logs
					$log_message = '[PagerDuty API Modified] user: '.$this->session->userdata('username').' | for client: '.$asset['client'];
					$this->utility->write_log_entry('info', $log_message);
					
					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', '<p>PagerDuty API settings were successfully updated.</p>');

					redirect('/customer-management/pagerduty/'.$asset['code']);
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				if (validation_errors()) 
				{
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', validation_errors());
				}
			}
		}
		
		# Page Data
		$data['client_code']	= $asset['code'];			
		$data['pagerduty_info']	= $this->pagerduty_api->redis_info($asset['seed_name']);
		
		# Page Views
		$this->load->view('assets/header');
		$this->load->view('customer-management/pagerduty/modify', $data);
		$this->load->view('assets/footer');
	}

	public function api_test()
	{
		$asset 		= client_redis_info_by_code();
		$response 	= $this->pagerduty_api->send_test_event($asset['seed_name']);

		if ($response['success'])
		{
			$this->pagerduty_api->redis_info($asset['seed_name'], $field = NULL, $action = 'SET', array('tested' => '1'));
			
			$return_array = array(
				'success' 	=> TRUE,
				'response'	=> $response['response']
			);
		}
		else
		{
			$return_array = array(
				'success' 	=> FALSE,
				'response' 	=> $response['response']
			);
		}

		echo json_encode($return_array);
	}

	public function activate()
	{
		$asset = client_redis_info_by_code();
		
		$pagerduty_info 				= $this->pagerduty_api->redis_info($asset['seed_name']);
		$data['authorized_to_modify']	= $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code'] 			= $asset['code'];
		$data['client_title']			= $asset['client'];
		$data['requested']				= $pagerduty_info['request_sent'];
		$data['request_user'] 			= $pagerduty_info['request_user'] ?? NULL;
		$data['terms_agreed'] 			= intval($pagerduty_info['terms_agreed']);

		$this->load->view('customer-management/pagerduty/activate', $data);
	}

	public function do_activate()
	{
		$asset = client_redis_info_by_code();
		
		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by 	= $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;
			
			if ($this->pagerduty_api->change_api_activation_status($asset['seed_name'], $requested_by, TRUE))
			{
				$this->account->send_api_activation_notification($asset['id'], 'pagerduty', $requested_name);
				
				# Write To Logs
				$log_message = '[PagerDuty API Enabled] user: '.$this->session->userdata('username').', has enabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);
				
				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The PagerDuty API for: <strong>'.$asset['client'].'</strong>, has been successfully enabled.</p>');

				$response = array(
					'success'	=> true,
					'goto_url'	=> '/customer-management/pagerduty/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'	=> false,
					'message'	=> '<p>Oops, something went wrong.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'	=> false,
					'message'	=> validation_errors()
				);
				echo json_encode($response);
			}
		}
	}

	public function disable()
	{
		$asset = client_redis_info_by_code();

		$data['authorized_to_modify']	= $this->account->get_authorized_to_modify($asset['id']);
		$data['client_code'] 			= $asset['code'];
		$data['client_title']			= $asset['client'];
		
		$this->load->view('customer-management/pagerduty/disable', $data);
	}

	public function do_disable()
	{
		$asset = client_redis_info_by_code();
		
		$this->form_validation->set_rules('requesting_user', 'Requesting Contact', 'trim|required');
		$this->form_validation->set_rules('api-terms-of-agreement', 'api-terms-of-agreement', 'trim|required');

		if ($this->form_validation->run()) 
		{
			$requested_by 	= $this->input->post('requesting_user');			
			$requested_user = $this->account->get_user_by_code($requested_by);
			$requested_name = $requested_user->first_name.' '.$requested_user->last_name;
			
			if ($this->pagerduty_api->change_api_activation_status($asset['seed_name'], $requested_by, FALSE))
			{
				$this->account->send_api_disabled_notification($asset['id'], 'pagerduty', $requested_name);
				
				# Write To Logs
				$log_message = '[PagerDuty API Disable] user: '.$this->session->userdata('username').', has disabled api for customer: '.$asset['client'].', per the request of '.$requested_name;
				$this->utility->write_log_entry('info', $log_message);
				
				# Set Success Alert Response
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>The PagerDuty API for: <strong>'.$asset['client'].'</strong>, has been successfully disabled.</p>');

				$response = array(
					'success'	=> true,
					'goto_url'	=> '/customer-management/pagerduty/'.$asset['code']
				);
				echo json_encode($response);
			}
			else
			{
				# Set Error
				$response = array(
					'success'	=> false,
					'message'	=> '<p>Oops, something went wrong.</p>'
				);
				echo json_encode($response);
			}
		}
		else
		{
			if (validation_errors()) 
			{
				# Set Error
				$response = array(
					'success'	=> false,
					'message'	=> validation_errors()
				);
				echo json_encode($response);
			}
		}
	}
}