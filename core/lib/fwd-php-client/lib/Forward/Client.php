<?php

namespace Forward
{
    /**
     * Thrown on client errors
     */
    class ClientException extends \Exception {}

    /**
     * Forward API Client
     */
    class Client
    {
        /**
         * Connection parameters
         * @var array
         */
        protected $params;

        /**
         * Client connection instance
         * @var Forward\Connection
         */
        protected $server;

        /**
         * Session identifier
         * @var string
         */
        protected $session;

        /**
         * Client cache instance
         * @var Forward\Cache
         */
        public $cache;

        /**
         * Default api server host
         * @static string
         */
        public static $default_host = "api.getfwd.com";

        /**
         * Default rescue server host
         * @static string
         */
        public static $default_rescue_host = "rescue.api.getfwd.com";

        /**
         * Default api server port (secure)
         * @static int
         */
        public static $default_port = 8443;

        /**
         * Default api server port (cleartext)
         * @static int
         */
        public static $default_clear_port = 8880;

        /**
         * Default rescue server port (secure)
         * @static int
         */
        public static $default_rescue_port = 8911;

        /**
         * Construct api client
         *
         * @param  string $client_id
         * @param  string $client_key
         * @param  array $options
         * @return void
         */
        function __construct($client_id, $client_key = null, $options = null)
        {
            if (is_array($client_id)) {
                $options = $client_id;
                $client_id = null;
            } else if (is_array($client_key)) {
                $options = $client_key;
                $client_key = null;
            }
            if ($client_id === null) {
                if (isset($options['client_id'])) {
                    $client_id = $options['client_id'];
                } else if (isset($options['id'])) {
                    $client_id = $options['id'];
                }
            }
            if ($client_key === null) {
                if (isset($options['client_key'])) {
                    $client_key = $options['client_key'];
                } else if (isset($options['key'])) {
                    $client_key = $options['key'];
                }
            }
            if (!isset($options['session']) || $options['session']) {
                if (!is_string($options['session'])) {
                    $options['session'] = session_id();
                }
            }
            if (isset($options['rescue']) && $options['rescue'] !== false) {
                $options['rescue'] = array(
                    'host' => isset($options['rescue']['host'])
                        ? $options['rescue']['host'] : self::$default_rescue_host,
                    'port' => isset($options['rescue']['port'])
                        ? $options['rescue']['port'] : self::$default_rescue_port
                );
            }
            $this->params = array(
                'client_id' => $client_id,
                'client_key' => $client_key,
                'host' => isset($options['host']) ? $options['host'] : self::$default_host,
                'port' => isset($options['port']) ? $options['port'] : self::$default_port,
                'clear' => isset($options['clear']) ? $options['clear'] : false,
                'clear_port' => isset($options['clear_port']) ? $options['clear_port'] : self::$default_clear_port,
                'verify_cert' => isset($options['verify_cert']) ? $options['verify_cert'] : true,
                'version' => isset($options['version']) ? $options['version'] : 1,
                'session' =>isset($options['session']) ? $options['session'] : null,
                'rescue' => isset($options['rescue']) ? $options['rescue'] : null,
                'api' => isset($options['api']) ? $options['api'] : null,
                'route' => isset($options['route']) ? $options['route'] : null,
                'proxy' => isset($options['proxy']) ? $options['proxy'] : null,
                'cache' => isset($options['cache']) ? $options['cache'] : null
            );

            $this->server = new \Forward\Connection(
                $this->params['host'],
                $this->params['clear'] ? $this->params['clear_port'] : $this->params['port'],
                array(
                    'clear' => $this->params['clear'],
                    'verify_cert' => $this->params['verify_cert']
                )
            );
        }

        /**
         * Get or set client params
         *
         * @param  mixed $merge
         * @param  array
         */
        public function params($merge = null)
        {
            if (is_array($merge)) {
                $this->params = array_merge($this->params, $merge);
            } else if (is_string($key = $merge)) {
                return $this->params[$key];
            } else {
                return $this->params;
            }
        }

        /**
         * Request helper
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        public function request($method, $url, $data = null)
        {
            $url = (string)$url;
            $data = array('$data' => $data);

            try {
                if (!$this->server->connected) {
                    if ($this->params['proxy']) {
                        $data = $this->request_proxy_data($data);
                    }
                    $this->server->connect();
                }
                $result = $this->server->request($method, array($url, $data));
            } catch (\Exception $e) {
                $this->request_rescue($e, array(
                    'method' => $method,
                    'url' => $url
                ));
            }

            if (isset($result['$auth'])) {
                if (isset($result['$end'])) {
                    // Connection ended, retry
                    return $this->request($method, $url, $data['$data']);
                } else {
                    $this->authed = true;
                    $result = $this->auth($result['$auth']);
                }
            }

            return $this->response($method, $url, $data, $result);
        }

        /**
         * Request from the rescue server
         *
         * @param  string $method
         * @param  string $url
         * @param  array $data
         * @return mixed
         */
        protected function request_rescue($e, $params)
        {
            if (!$e) {
                return;
            }
            if (isset($this->params['is_rescue'])) {
                // TODO: cache exceptions until rescue server responds
                return;
            }
            if ($this->params['rescue'] && $this->params['client_id'] && $this->params['client_key']) {

                if (!$this->rescue) {
                    $this->rescue = new Client(
                        $this->params['client_id'],
                        $this->params['client_key'],
                        $this->params['rescue']
                    );
                    $this->rescue->params(array('is_rescue' => true));
                }

                $last_request_id = $this->server->request_id() ?: $this->server->request_id(true);

                $result = $this->rescue->post("/rescue.exceptions", array(
                    'type' => end(explode('\\', get_class($e))),
                    'message' => $e->getMessage(),
                    'request' => array(
                        'id' => $last_request_id,
                        'params' => $params
                    )
                ));

                if ($result) {
                    $e_message = "(System alerted with Exception ID: {$result['id']})";
                    $e_class = get_class($e);
                    throw new $e_class($e->getMessage().' '.$e_message, $e->getCode(), $e);
                }
            }

            throw $e;
        }

        /**
         * Modify request to pass through an API proxy
         *
         * @param  array $data
         * @return array
         */
        protected function request_proxy_data($data)
        {
            if (property_exists($this, 'is_rescue') && $this->is_rescue) {
                return $data;
            }

            $data['$proxy'] = array(
                'client' => $this->params['route']
                    ? $this->params['route']['client']
                    : $this->params['client_id'],
                'host' => $this->params['host'],
                'port' => $this->params['port']
            );
            if (is_array($this->params['proxy'])) {
                // Set connection to proxy host/port + cleartext
                $this->server->options['clear'] = true;
                $this->server->host = isset($this->params['proxy']['host'])
                    ? $this->params['proxy']['host'] : $this->params['host'];
                $this->server->port = isset($this->params['proxy']['clear_port'])
                    ? $this->params['proxy']['clear_port']: $this->params['clear_port'];
            }
            if ($this->params['cache'] && !$this->cache) {
                $client_id = $data['$proxy']['client'];
                $this->cache = new \Forward\Cache($client_id, $this->params['cache']);
                $data['$cached'] = $this->cache->get_versions();
            }

            return $data;
        }

        /**
         * Response helper
         *
         * @param  string $method
         * @param  string $url
         * @param  mixed $data
         * @param  mixed $result
         * @return Forward\Resource
         */
        protected function response($method, $url, $data, $result)
        {
            if ($this->cache) {
                $this->cache->clear($result);
                if ($method === 'get' && $url !== '/:sessions/:current') {
                    $this->cache->put($url, $data, $result);
                }
            }

            return $this->response_data($result, $method, $url);
        }

        /**
         * Instantiate resource for response data if applicable
         *
         * @param  array $result
         * @return mixed
         */
        protected function response_data($result, $method, $url)
        {
            if (isset($result['$data'])) {
                if (is_array($result['$data'])) {
                    if (!isset($result['$url'])) {
                        // Default resource url
                        if ($method === 'post') {
                            $url = rtrim($url, '/').'/'.$result['$data']['id'];
                        }
                        $result['$url'] = $url;
                    }
                    return Resource::instance($result, $this);
                }
                return $result['$data'];
            }
            return null;
        }

        /**
         * Call GET method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function get($url, $data = null)
        {
            if ($this->cache) {
                $result = $this->cache->get($url, array('$data' => $data));
                if (array_key_exists('$data', (array)$result)) {
                    return $this->response_data($result, 'get', $url);
                }
            }

            return $this->request('get', $url, $data);
        }

        /**
         * Call PUT method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function put($url, $data = '$undefined')
        {
            if ($data === '$undefined') {
                $data = ($url instanceof Resource)
                    ? $url->data()
                    : null;
            }
            return $this->request('put', $url, $data);
        }

        /**
         * Call POST method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function post($url, $data = null)
        {
            return $this->request('post', $url, $data);
        }

        /**
         * Call DELETE method
         *
         * @param  string $url
         * @param  mixed $data
         * @return mixed
         */
        public function delete($url, $data = null)
        {
            return $this->request('delete', $url, $data);
        }

        /**
         * Call AUTH method
         *
         * @param  string $nonce
         * @return mixed
         */
        public function auth($nonce = null)
        {
            $client_id = $this->params['client_id'];
            $client_key = $this->params['client_key'];

            // 1) Get nonce
            $nonce = $nonce ?: $this->server->request('auth');

            // 2) Create key hash
            $key_hash = md5("{$client_id}::{$client_key}");

            // 3) Create auth key
            $auth_key = md5("{$nonce}{$client_id}{$key_hash}");

            // 4) Authenticate with client creds and options
            $creds = array(
                'client' => $client_id,
                'key' => $auth_key
            );
            if ($this->params['version']) {
                $creds['$v'] = $this->params['version'];
            }
            if ($this->params['api']) {
                $creds['$api'] = $this->params['api'];
            }
            if ($this->params['session']) {
                $creds['$session'] = $this->params['session'];
            }
            if ($this->params['route']) {
                $creds['$route'] = $this->params['route'];
            }
            if ($ip_address = $_SERVER['REMOTE_ADDR']) {
                $creds['$ip'] = $ip_address;
            }
            if ($this->params['cache'] && !$this->cache) {
                $client_id = isset($creds['$route']['client']) ? $creds['$route']['client'] : $client_id;
                $this->cache = new \Forward\Cache($client_id, $this->params['cache']);
                $creds['$cached'] = $this->cache->get_versions();
            }
            
            try {
                return $this->server->request('auth', array($creds));
            } catch (\Exception $e) {
                $this->request_rescue($e, array(
                    'method' => 'auth',
                    'data' => $creds
                ));
            }
        }
    }
}
