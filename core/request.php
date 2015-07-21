<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Request
{
    /**
     * @var array
     */
    private static $meta;

    /**
     * @var \Schema\Session
     */
    private static $session;

    /**
     * @var \Schema\Client
     */
    private static $client;

    /**
     * @var \Schema\Settings
     */
    private static $settings;

    /**
     * Dispatched request vars
     * @var array
     */
    private static $vars;

    /**
     * Get current request vars
     *
     * @return array
     */
    public static function current()
    {
        return self::$vars;
    }

    /**
     * Dispatch a request
     *
     * @param  mixed $url
     * @param  array $routes
     * @param  bool $return
     * @return mixed
     */
    public static function dispatch($url = null, $routes = null, $return = false)
    {
        // Sanitize and parse request
        $request = self::parse($url);
        $request = self::route($request, $routes ?: Config::get('routes'));
        $request = Event::trigger('request', 'dispatch', $request);
        
        // Route the template
        $request = Template::route($request);

        if (!is_dir($request['template_path'])) {
            return Request::dispatch_notfound_template($request);
        }

        // Handle request redirects
        if (isset($request['redirect'])) {
            self::redirect($request['redirect']);
        }

        // Load the template
        Template::load($request);

        // Route the view
        $request = View::route($request);
        if (!is_file($request['view_path'])) {
            return Request::dispatch_notfound_view($request);
        }

        // Merge request vars
        $request = self::$vars = array_merge((array)self::$vars, $request);

        // Restore persisted request vars
        Request::restore();

        // Render the view
        $request = Request::render($request);

        // Response status may be explicit
        if (isset($request['status'])) {
            Request::status($request['status']);
        }
        // Result may be a pre-defined status code (i.e. 500)
        if (is_int($request['result'])) {
            return Request::dispatch_code($request['result'], $request, $return);
        }

        return ($return ? $request['result'] : print $request['result']);
    }

    /**
     * Handle template not found error
     *
     * @param  array $request
     * @return void
     */
    private static function dispatch_notfound_template($request)
    {
        throw new \Exception("Template not found at {$request['template_path']}", 500);
    }

    /**
     * Handle view not found error
     *
     * @param  array $request
     * @return mixed
     */
    private static function dispatch_notfound_view($request, $return  = false)
    {
        $request['output'] = 'html';
        $code_result = Request::dispatch_code(404, $request, true);
        if ($code_result === 404) {
            throw new \Exception("View not found at {$request['view_path']}", 404);
        }
        return ($return ? $code_result : print $code_result);
    }

    /**
     * Dispatch a response by code
     *
     * @param  array $request
     * @param  bool $return
     * @return mixed
     */
    private static function dispatch_code($code, $request, $return = false)
    {
        Request::status($code);

        $orig_path = $request['path'];
        $orig_args = $request['args'];

        $code_request = $request;
        $code_request['view'] = null;
        $code_request['orig'] = null;
        $code_request['path'] = '/'.$code;
        $code_request = View::route($code_request);

        $code_request['path'] = $orig_path;
        $code_request['args'] = $orig_args;

        // Render code view if exists
        if (is_file($code_request['view_path'])) {
            $code_request = Request::render($code_request);
        }

        $result = isset($code_request['result']) ? $code_request['result'] : $code;

        return ($return ? $result : print $result);
    }

    /**
     * Set response status header for the request
     *
     * @param  int $code
     */
    private static function status($code)
    {
        $recognized_codes = array(
            200 => "OK",
            201 => "Created",
            202 => "Accepted",
            203 => "Non-Authoritative Information",
            204 => "No Content",
            205 => "Reset Content",
            206 => "Partial Content",
            207 => "Multi-Status",
            300 => "Multiple Choices",
            301 => "Moved Permanently",
            302 => "Found",
            303 => "See Other",
            304 => "Not Modified",
            305 => "Use Proxy",
            306 => "(Unused)",
            307 => "Temporary Redirect",
            308 => "Permanent Redirect",
            400 => "Bad Request",
            401 => "Unauthorized",
            402 => "Payment Required",
            403 => "Forbidden",
            404 => "Not Found",
            405 => "Method Not Allowed",
            406 => "Not Acceptable",
            407 => "Proxy Authentication Required",
            408 => "Request Timeout",
            409 => "Conflict",
            410 => "Gone",
            411 => "Length Required",
            412 => "Precondition Failed",
            413 => "Request Entity Too Large",
            414 => "Request-URI Too Long",
            415 => "Unsupported Media Type",
            416 => "Requested Range Not Satisfiable", 
            417 => "Expectation Failed",
            418 => "I'm a teapot",
            419 => "Authentication Timeout",
            420 => "Enhance Your Calm",
            422 => "Unprocessable Entity",
            423 => "Locked",
            424 => "Failed Dependency",
            424 => "Method Failure",
            425 => "Unordered Collection",
            426 => "Upgrade Required",
            428 => "Precondition Required",
            429 => "Too Many Requests",
            431 => "Request Header Fields Too Large",
            500 => "Internal Server Error",
            501 => "Not Implemented",
            502 => "Bad Gateway",
            503 => "Service Unavailable",
            504 => "Gateway Timeout"
        );
        if (isset($recognized_codes[$code])) {
            $code_text = $recognized_codes[$code];
            header("HTTP/1.0 {$code} {$code_text}");
        } else {
            throw new \Exception("Response code '{$code}' not recognized");
        }
    }

    /**
     * Route a request against config
     *
     * @param  array $request
     * @param  array $routes
     * @return array $request
     */
    public static function route($request, $routes = null)
    {
        if (empty($routes) || !is_array($routes)) {
            return $request;
        }

        foreach ($routes as $key => $route) {
            $route = self::route_format($key, $route);

            if (isset($route['match'])) {
                foreach ((array)$route['match'] as $match_key => $match_value) {
                    if (!isset($request[$match_key])) {
                        continue;
                    }
                    if (!self::route_match($match_value, $request[$match_key])) {
                        continue(2);
                    }
                }
            }

            $is_secure = $request['secure'] ? true : false;

            // Merge route values with request
            if (isset($route['request'])) {
                $request = array_merge($request, (array)$route['request']);
            }

            // Special cases
            if (isset($request['break']) && $request['break']) {
                break;
            }
            if (isset($request['scheme']) && $request['scheme'] === 'https') {
                $request['secure'] = true;
            }
            if ($request['secure'] && !$is_secure) {
                $request['redirect'] = "https://{$request['host']}{$request['path']}";
                if ($request['query']) {
                    $request['redirect'] .= "?{$request['query']}";
                }
            }
            if (isset($request['redirect']) && $request['redirect']) {
                break;
            }
        }

        return $request;
    }

    /**
     * Format route based on conventions
     *
     * @param  string $key
     * @param  mixed $route
     */
    private static function route_format($key, $route)
    {
        if (is_string($key) && is_string($route)) {
            $route = array(
                'match' => array('uri' => $key),
                'request' => array('path' => $route)
            );
        } else if (is_string($key) && is_array($route) && !$route['match']) {
            $route['match'] = array('uri' => $key);
        }

        if (isset($route['match']) && !is_array($route['match'])) {
            $route['match'] = array('uri' => $route['match']);
        }

        return $route;
    }

    /**
     * Determine if route test matches request value
     *
     * @param  string $test_val
     * @param  string $request_val
     * @return bool
     */
    private static function route_match($test_val, $request_val)
    {
        if ($test_val === $request_val) {
            return true;
        }

        $expr = $test_val;
        $expr = preg_replace('/\.([^\?\+\*])/', '\.\\1', $expr);
        $expr = str_replace('/', '\/', str_replace('\/', '/', $expr));

        if ($expr) {
            $first = $expr[0];
            $first2 = substr($expr, 0, 2);
            $last = substr($expr, -1, 1);
            $last2 = substr($expr, -2, 2);

            if ($first !== "^" && $last !== "$") {
                if ($first === '*') {
                    $expr = "[^\\.]{$expr}";
                }
                if ($first !== "^" && $first2 === "\/" && strlen($expr) > 2) {
                    $expr = "^{$expr}";
                } else if ($last !== "$" && $last2 === "\/") {
                    $expr = "{$expr}$";
                } else {
                    $expr = "^{$expr}$";
                }
            }

            $handle_syntax_error = function($errno, $errstr, $errfile, $errline, array $errcontext)
            use($test_val, $expr)
            {
                Util\error_handler($errno, "Invalid route expression '".$test_val."' ({$expr})");
                restore_error_handler();
            };
            set_error_handler($handle_syntax_error);

            $matched = false;
            try {
                if (preg_match("/{$expr}/", $request_val)) {
                    $matched = true;
                }
            }
            catch (\Exception $e) {}
            restore_error_handler();
            return $matched;
        }

        return false;
    }

    /**
     * Render a request view, returning the request with result value
     *
     * @param  array $request
     * @return array
     */
    public static function render($request)
    {
        $request = Event::trigger('request', 'render', $request);
        $request['result'] = View::render($request);
        $request = Event::trigger('request', 'rendered', $request);

        return $request;
    }

    /**
     * Redirect request to a new location
     *
     * @param  string $url
     * @param  bool $permanent
     * @return void
     */
    public static function redirect($url, $permanent = false)
    {
        self::persist();

        $url = Event::trigger('request', 'redirect', $url);
        
        if (self::$vars['ajax']) {
            $url .= (strpos($url, '?') === false ? '?__ajax' : '&__ajax');
        }

        header("Location: {$url}", true, $permanent ? 301 : 302);

        exit();
    }

    /**
     * Build and sanitize a request URL
     *
     * @param  mixed $url
     * @param  array $parts
     * @return string
     */
    public static function url($url, $parts = array())
    {
        // TODO: determine if this comes with cURL or if it needs to be replaced
        $url = $url ?: (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        $uri_parts = parse_url($url);

        $default_parts = array(
            'scheme' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http',
            'host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'path' => $uri_parts['path'],
            'query' => isset($uri_parts['query']) ? $uri_parts['query'] : null
        );

        $parts = array_merge($default_parts, $parts);

        $sane_url = "{$parts['scheme']}://{$parts['host']}{$parts['path']}"
            .($parts['query'] ? '?'.$parts['query'] : '');

        return $sane_url;
    }

    /**
     * Parse a url into a request array
     *
     * @param  string $url
     * @return array
     */
    public static function parse($url)
    {
        $url = self::url($url);
        $request = parse_url($url);

        // Parse base uri path
        $uri_path = rtrim(Config::path('uri'), '/').'/';

        // Remove base uri from request path
        if (strpos($request['path'], $uri_path) === 0) {
            $request['path'] = preg_replace('#^'.$uri_path.'#', '', $request['path']);
            $request['path'] = '/'.ltrim($request['path'], '/');
        }
        if (!isset($request['path'])) {
            $request['path'] = '/';
        }

        // Query
        $request['query'] = isset($request['query']) ? $request['query'] : null;

        // URL and URI
        $request['url'] = $url;
        $request['uri'] = $request['path'].($request['query'] ? '?'.$request['query'] : '');

        // Method
        $request['method'] = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'get';
        $request['get'] = $request['method'] == 'get';
        $request['post'] = $request['method'] == 'post';
        $request['ajax'] = (
            (isset($_SERVER["HTTP_X_REQUESTED_WITH"])
                && $_SERVER["HTTP_X_REQUESTED_WITH"] == 'XMLHttpRequest')
            || isset($_REQUEST['__ajax'])
        ) ? true : false;

        // Secure/HTTPS
        $request['secure'] = !isset($_SERVER['HTTP_HOST'])
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return $request;
    }

    /**
     * Bind to a request method event
     *
     * @param  string $method
     * @param  mixed $match
     * @param  closure $handler
     * @return void
     */
    public static function bind($method, $match, $handler = null)
    {
        if (is_callable($match)) {
            $handler = $match;
            $match = null;
        }
        if (!is_callable($handler)) {
            throw new \Exception('Bind handler is not a function');
        }
        Event::bind("request.{$method}", function() use($method, $match, $handler)
        {
            if (is_array($match)) {
                // Filter binding based on conditional match
                $request = Request::route(Request::current(), array(
                    'match' => $match,
                    'request' => array('matched' => true)
                ));
                if ($request['matched']) {
                    $result = call_user_func_array($handler, func_get_args());
                    if (!is_null($result)) {
                        return $result;
                    }
                }
            } else {
                $result = call_user_func_array($handler, func_get_args());
                if (!is_null($result)) {
                    return $result;
                }
            }
        });
    }

    /**
     * Setup request
     *
     * @param  array $options
     * @return void
     */
    public static function setup()
    {
        require_once(Config::path('core', 'lib/schema-php-client/lib/Schema.php'));
        
        set_error_handler('\\Schema\\Util\error_handler', error_reporting());
        set_exception_handler('\\Schema\\Util\exception_handler');

        Request::setup_locale();

        Request::setup_autoloaders();

        Request::setup_helpers();

        Request::setup_plugins();
    }

    /**
     * Setup default or configured locale settings
     */
    public static function setup_locale()
    {
        // TODO: make this configurable
        setlocale(LC_ALL, "en_US.UTF-8");
    }

    /**
     * Set up autoloaders
     *
     * @return void
     */
    public static function setup_autoloaders()
    {
        // Basic autoload util
        spl_autoload_register('\\Schema\\Util\autoload');

        // Use composer autoloader if present
        $composer_autoloader = Config::path('root', 'vendor/autoload.php');
        if (is_file($composer_autoloader)) {
            require $composer_autoloader;
        }
    }

    /**
     * Load enabled plugins
     *
     * @return void
     */
    public static function setup_plugins()
    {
        Plugin::load(
            // path to global plugins
            Config::path('plugins'),
            // global plugin config
            Config::get('plugins')
        );
    }

    /**
     * Load core helpers
     *
     * @return void
     */
    public static function setup_helpers()
    {
        Helper::core();
    }

    /**
     * Initiate a client request
     *
     * @param  string $method
     * @param  string $url
     * @param  array $data
     * @return mixed
     */
    public static function client_request($method, $url, $data = null)
    {
        $params = array(
            'method' => &$method,
            'url' => &$url,
            'data' => &$data
        );
        // This approach enables two different event types:
        // 1) Request::bind('remote', array(... request filter ...), function($params){})
        // 2) Event::bind('client.request', function($params){})
        $params = Event::trigger('request', 'remote', $params);
        $params = Event::trigger('client', 'request', $params);

        if (is_array($params)) {
            $method = $params['method'] ?: $method;
            $url = $params['url'] ?: $url;
            $data = $params['data'] ?: $data;
        }

        try {
            $response = self::client()->{$method}($url, $data);
        } catch (ServerException $e) {
            $message = $e->getMessage()." (".$method." ".$url." ".json_encode($data ?: new \stdClass).")";
            throw new ServerException($message);
        }

        $response = Event::trigger('client', 'response', $response, $params);
        
        return $response;
    }

    /**
     * Get client configuration
     *
     * @return array
     */
    public static function client_config()
    {
        $config = Config::get(array(
            'client',
            'client_id',
            'client_key',
            'client_host',
            'client_port',
            'client_clear',
            'client_clear_port',
            'client_verify_cert',
            'client_session',
            'client_version',
            'client_api',
            'client_rescue',
            'client_proxy',
            'client_cache',
            'clients'
        ));

        $request_client = array();
        if (isset(self::$vars['client'])) {
            $request_client = self::$vars['client'];
            if (is_string($request_client)) {
                $request_client = isset($config['clients'][$request_client])
                    ? $config['clients'][$request_client]
                    : array();
            }
        }
        $config_client = array();
        if (isset($config['client'])) {
            $config_client = $config['client'];
            if (is_string($config_client)) {
                $config_client = isset($config['clients'][$config_client])
                    ? $config['clients'][$config_client]
                    : array();
            }
        }
        $client = array_merge(
            $config_client,
            $request_client
        );
        $client_config = array(
            'id' => isset($client['id']) ? $client['id'] : $config['client_id'],
            'key' => isset($client['key']) ? $client['key'] : $config['client_key'],
            'host' => isset($client['host']) ? $client['host'] : $config['client_host'],
            'port' => isset($client['port']) ? $client['port'] : $config['client_port'],
            'clear' => isset($client['clear']) ? $client['clear'] : $config['client_clear'],
            'clear_port' => isset($client['clear_port']) ? $client['clear_port'] : $config['client_clear_port'],
            'version' => isset($client['version']) ? $client['version'] : $config['client_version'],
            'api' => isset($client['api']) ? $client['api'] : $config['client_api'],
            'proxy' => isset($client['proxy']) ? $client['proxy'] : $config['client_proxy'],
            // Following options may be set 'false', other they default truthy
            'verify_cert' => isset($client['verify_cert'])
                ? $client['verify_cert'] : $config['client_verify_cert'],
            'rescue' => isset($client['rescue'])
                ? $client['rescue'] : $config['client_rescue'],
            'cache' => isset($client['cache'])
                ? $client['cache'] : $config['client_cache'],
            'session' => isset($client['session'])
                ? $client['session'] : $config['client_session']
        );
        // Get client id and key from ENV if not explicit
        if (empty($client_config['id'])) {
            $client_config['id'] = getenv('client_id');
        }
        if (empty($client_config['key'])) {
            $client_config['key'] = getenv('client_key');
        }
        // Default cache enabled
        if (!isset($client_config['cache'])) {
            $client_config['cache'] = true;
        }
        if (is_bool($client_config['cache']) && $client_config['cache']) {
            $client_config['cache'] = array();
        }
        if (!isset($client_config['cache']['path']) && is_array($client_config['cache'])) {
            $client_config['cache']['path'] = Config::path('core', '/cache');
        }

        return $client_config;
    }

    /**
     * Get client adapter
     *
     * @return \Schema\Client
     */
    public static function client()
    {
        if (!self::$client) {
            $config = self::client_config();
            self::$client = new \Schema\Client($config['id'], $config['key'], $config);
            self::$client = Event::trigger('request', 'client', self::$client);
        }

        return self::$client;
    }

    /**
     * Get client settings
     *
     * @return \Schema\Settings
     */
    public static function settings()
    {
        if (!self::$settings) {
            self::$settings = new \Schema\Settings(self::client());
        }

        return self::$settings;
    }

    /**
     * Get request params as a combined array value
     *
     * @return array
     */
    public static function params()
    {
        return array_merge((array)$_GET, (array)$_POST);
    }

    /**
     * Get request session instance
     *
     * @return array
     */
    public static function session()
    {
        return self::$session ?: self::$session = new Session();
    }

    /**
     * Set a flash message
     *
     * @param  string $severity
     * @param  string $message
     * @param  string $redirect
     * @return void
     */
    public static function message($severity, $message, $redirect = null)
    {
        $message_type = $severity;
        $severity_map = array(
            'notice' => 'notices',
            'warning' => 'warnings',
            'error' => 'errors'
        );
        if (isset($severity_map[$severity])) {
            $message_type = $severity_map[$severity];
        }
        if (!in_array($message_type, array('notices', 'warnings', 'errors'))) {
            throw new Exception("Message type not valid '{$message_type}'");
        }
        if (!isset(self::$vars[$message_type])) {
            self::$vars[$message_type] = array();
        }
        if (is_string($message)) {
            $message = array($message);
        }
        foreach ($message as $msg) {
            array_push(self::$vars[$message_type], $msg);
        }
        if ($redirect) {
            self::redirect($redirect);
        }
    }

    /**
     * Persist request vars in session
     *
     * @return void
     */
    public static function persist()
    {
        $messages = array();
        if (isset(self::$vars['errors'])) {
            $messages['errors'] = self::$vars['errors'];
        }
        if (isset(self::$vars['warnings'])) {
            $messages['warnings'] = self::$vars['warnings'];
        }
        if (isset(self::$vars['notices'])) {
            $messages['notices'] = self::$vars['notices'];
        }
        if (!empty($messages)) {
            $session = self::session();
            $session['__messages'] = $messages;
        }
    }

    /**
     * Restore persisted request vars from session
     *
     * @return void
     */
    public static function restore()
    {
        $session = self::session();
        if (isset($session['__messages'])) {
            foreach ((array)$session['__messages'] as $severity => $message) {
                self::message($severity, $message);
            }
            unset($session['__messages']);
        }
    }
}

