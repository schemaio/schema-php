<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Session extends Util\ArrayInterface
{
    /**
     * Session URI
     * @var string
     */
    private static $uri;

    /**
     * Session data
     * @var array
     */
    private static $data = array();

    /**
     * Session data key
     * @var string
     */
    private static $data_key = '';

    /**
     * Session read/write error
     * @var string
     */
    private static $error = null;

    /**
     * Singleton constructor
     *
     * @return Session
     */
    public function __construct()
    {
        self::start();
        parent::__construct($_SESSION);
    }

    /**
     * Set the value of a session parameter using array notation
     *
     * @param  string $key
     * @param  mixed $val
     */
    public function offsetSet($key, $val)
    {
        $_SESSION[$key] = $val;
        return parent::offsetSet($key, $val);
    }

    /**
     * Get the value of a session parameter using path notation
     *
     * @param  string $path
     * @param  mixed $default
     * @return mixed
     */
    public static function get($path, $default = null)
    {
        return self::resolve($path) ?: $default;
    }

    /**
     * Set the value of a session parameter using path notation
     *
     * @param  string $path
     * @param  mixed $value
     * @return mixed
     */
    public static function set($path, $value)
    {
        $session_value =& self::resolve($path);
        $session_value = $value;
    }

    /**
     * Resolve path/dot-notation to a session parameter
     *
     * @param  string $path
     * @return mixed
     */
    public static function & resolve($path)
    {
        if (empty($path)) {
            return null;
        }

        $current =& $_SESSION;
        $p = strtok($path, '.');

        while ($p !== false) {
            if (!isset($current[$p])) {
                return null;
            }
            $current =& $current[$p];
            $p = strtok('.');
        }

        return $current;
    }

    /**
     * Start the session
     *
     * @return void
     */
    public static function start()
    {
        if (session_id() != '') {
            // Already started
            return;
        }
        if (!isset($_SERVER['HTTP_HOST'])) {
            // Command line env has no session
            $_SESSION = array();
            return;
        }
        $client = Request::client_config();
        if (!isset($client['session']) || $client['session']) {
            session_set_save_handler(
                function(){return true;},
                function(){return true;},
                '\\Schema\\Session::read',
                '\\Schema\\Session::write',
                '\\Schema\\Session::destroy',
                function(){return true;}
            );
        }
        session_start();
    }

    /**
     * Session save handler: read
     *
     * @param  string $session_id
     * @return bool
     */
    public static function read($session_id)
    {
        if (self::$uri === null) {
            self::$uri = '/:sessions/'.session_id();
        }
        try {
            self::$data = Request::client_request('get', self::$uri);
        } catch (\Exception $e) {
            self::$error = $e->getMessage();
            if (strpos(self::$error, 'trial expired') >= 0) {
              $_SESSION = array('_EXPIRED' => true);
              return;
            }
            throw $e;
        }
        foreach ((array)self::$data as $key => $val) {
            $_SESSION[$key] = $val;
        }
        self::$data_key = md5(json_encode($_SESSION));
        return true;
    }

    /**
     * Session save handler: write
     *
     * @param  string $session_id
     * @param  array $data
     * @return bool
     */
    public static function write($session_id, $data)
    {
        if (!self::$error) {
            $is_changed = (md5(json_encode($_SESSION)) != self::$data_key);
            if ($is_changed) {
                Request::client_request('put', self::$uri, array(
                    '$replace' => $_SESSION
                ));
            }
            return true;
        }
        return false;
    }

    /**
     * Session save handler: destroy
     *
     * @param  string $session_id
     * @return bool
     */
    public static function destroy($session_id)
    {
        Request::client_request('delete', self::$uri);
        return true;
    }
}
