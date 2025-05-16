<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Pagerduty_api 
{
	const API_URL = 'https://events.pagerduty.com/v2/enqueue';

	private $ch;
	private $headers = [];
    private $redis_host;
	private $redis_port;
	private $redis_timeout;  
	private $redis_password;
    private $client_redis_key;
	
    public function __construct()
	{
		$CI =& get_instance();		
		
		$this->redis_host 		= $CI->config->item('redis_host');
		$this->redis_port 		= $CI->config->item('redis_port');
        $this->redis_timeout 	= $CI->config->item('redis_timeout');
		$this->redis_password 	= $CI->config->item('redis_password');
        $this->client_redis_key	= 'pagerduty_';
	}

	public function redis_info($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info 	= client_redis_info($client);
		$client_key 	= $this->client_redis_key.$client;

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);
		
		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}     
		    
		$redis->close();
		
		if (empty($check))
		{
			$check = NULL;
		}
		
		return $check;		
	}

	public function create_pagerduty_redis_key($client, $data = NULL)
	{
		$client_info	= client_redis_info($client);
		$client_key 	= $this->client_redis_key.$client;
		
		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		$check = $redis->hMSet($client_key, [
			'routing_key'	=> $data['routing_key'],
			'tested'		=> '0',
			'request_sent'	=> '0',
			'enabled'		=> '0',
			'terms_agreed'	=> '0',
			'counter'		=> '1'
		]);
			   		    
		$redis->close();
		
		return $check;		
	}

	public function send_test_event($client)
    {
	    $pagerduty_info = $this->redis_info($client);
		$counter		= intval($pagerduty_info['counter']);

		$payload = array(
            'summary'	=> 'This is a test event',
			'severity'	=> 'info',
			'source'	=> $client.'.quadrantsec.com',
			'custom_details' => [
				'count'	=> $counter
			]
		);

        $response = $this->call_api($payload, $pagerduty_info['routing_key']);
		
        if ($response['result'] !== FALSE)
		{
            if ($response['http_code'] === 202)
			{
				$this->increment_test_counter($client);

                return array(
					'success'	=> TRUE,
					'response'	=> $response['result']
				);
			}
			else
			{
                return array(
					'success'	=> FALSE,
					'response'	=> $response['result']
				);
			}
		}
		else
		{
            return array(
		        'success' 	=> FALSE,
	        	'response' 	=> 'cURL errno: '.$response['errno'].', cURL error: '.$response['error']
	        );
		}
    }

	public function increment_test_counter($client)
	{
		$client_info 	= client_redis_info($client);
		$client_key 	= $this->client_redis_key.$client;
		
		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);
			
		$check = $redis->HINCRBY($client_key, 'counter', 1);
  		    
		$redis->close();
		
		return $check;		
	}

	public function send_event($client, $payload, $event_action = 'trigger', $dedup_key = NULL)
	{
        $pagerduty_info = $this->redis_info($client);

		$response = $this->call_api($payload, $pagerduty_info['routing_key'], $event_action, $dedup_key);
		
        if ($response['result'] !== FALSE)
		{
            if ($response['http_code'] === 202)
			{
                return array(
					'success'	=> TRUE,
					'response'	=> $response['result']
				);
			}
			else
			{
                return array(
					'success'	=> FALSE,
					'response'	=> $response['result']
				);
			}
		}
		else
		{
            return array(
		        'success' 	=> FALSE,
	        	'response' 	=> 'cURL errno: '.$response['errno'].', cURL error: '.$response['error']
	        );
		}
	}

    public function call_api($payload, $routing_key, $event_action = 'trigger', $dedup_key = NULL)
    {
		$header_fields = array(
			'Content-Type: application/json'
		);

		$post_fields = array(
            'payload' 		=> $payload,
            'routing_key' 	=> $routing_key,
            'event_action' 	=> $event_action
		);

		if ($event_action === 'trigger')
		{
            $post_fields['client'] 		= 'Quadrant';
			//$post_fields['client_url'] 	= 'TBD';
		}
		else if (!is_null($dedup_key))
		{
            $post_fields['dedup_key'] = $dedup_key;
		}

		$this->ch = curl_init();

		curl_setopt($this->ch, CURLOPT_URL, self::API_URL);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header_fields);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
	    //curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) {
                $len = strlen($header);

                $header = explode(':', $header, 2);

                if (count($header) < 2) // ignore invalid headers
				{
                    return $len;
				}

                $name = strtolower(trim($header[0]));

                if (!array_key_exists($name, $this->headers))
				{
                    $this->headers[$name] = [trim($header[1])];
				}
                else
				{
                    $this->headers[$name][] = trim($header[1]);
				}

                return $len;
            }
        );

        if (($response['result'] = curl_exec($this->ch)) !== FALSE)
		{
            if (array_key_exists('content-length', $this->headers) && intval($this->headers['content-length'][0]))
			{
                $response['result'] = json_decode($response['result'], TRUE);
			}

			$response['http_code'] 	= curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			//$response['headers'] 	= $this->headers;
		}
		else
		{
            $response['errno'] 	= curl_errno($this->ch);
            $response['error'] 	= curl_error($this->ch);
		}

		curl_close($this->ch);

        return $response;
	}

	public function change_api_activation_status($client, $requested, $status)
	{
		$set_activation = ($status) ? 1 : 0;
		$check 			= FALSE;
		
		#set soc redis keys
		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

		$check = $redis->hSet($client.'_information', 'pagerduty_enabled', $set_activation);
			
		$redis->close();

		# set client redis keys
		if (is_int($check))
		{
			$status_data = array(
				'enabled'		=> $set_activation,
				'request_sent' 	=> $set_activation,
				'request_user'	=> $requested,
				'terms_agreed'	=> $set_activation
			);

			$config_data = array(
				'pagerduty_enabled' => $set_activation
			);
			
			if ($this->redis_info($client, $field = NULL, $action = 'SET', $status_data))
			{
				if ($this->client_config($client, $field = NULL, $action = 'SET', $config_data))
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}
	
	public function client_config($client, $field = NULL, $action = 'GET', $data = NULL)
	{
		$client_info 	= client_redis_info($client);
		$client_key 	= $client.'_configurations';

		$redis = new Redis();
		$redis->connect($client_info['redis_host'], $client_info['redis_port'], $this->redis_timeout);
		$redis->auth($client_info['redis_password']);

		if ($action === 'SET')
		{
			$check = $redis->hMSet($client_key, $data);
		}
		else
		{
			if (is_null($field))
			{
				$check = $redis->hGetAll($client_key);
			}
			else
			{
				$check = $redis->hGet($client_key, $field);
			}
		}   
		    
		$redis->close();
		
		if (empty($check))
		{
			$check = NULL;
		}
		
		return $check;		
	}
}