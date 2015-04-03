<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Plugin
{
    /**
     * Plugin registry
     * @var array
     */
    private static $registry;

    /**
     * Flag indicating discovery mode
     * @var bool
     */
    private static $discover;

    /**
     * Find and discover plugin directories by file path
     *
     * @param  string $path
     * @return array
     */
    public static function find($path)
    {
        $plugins = array();
        foreach (scandir($path) as $plugin) {
            if ($plugin[0] != "." && is_dir($path.'/'.$plugin)) {
                $plugins[$plugin] = array(
                    'enabled' => true
                );
            }
        }
        return $plugins;
    }

    /**
     * Load all configured/enabled
     *
     * @param  string $path
     * @param  array $ext_plugins (optional plugin config to override)
     * @return void
     */
    public static function load($path = null, $ext_plugins = null)
    {
        $path = $path ?: Config::path('plugins');

        $plugins = self::find($path);

        if (!empty($ext_plugins)) {
            $plugins = Util\merge($plugins, $ext_plugins);
        }

        foreach ((array)$plugins as $plugin => $settings) {
            if (!$settings['enabled']) continue;

            $load_path = $path.'/'.$plugin.'/plugin.php';
            $config_path = $path.'/'.$plugin.'/plugin.json';
            $plugin_config = self::config($plugin, $config_path);

            self::$registry[$plugin] = Util\merge($plugin_config, $settings);

            // Enable discovery?
            self::$discover = $plugin;
            require_once $load_path;
            self::$discover = $plugin;
        }
    }

    /**
     * Get plugin configuration
     *
     * @param  string $plugin
     * @return array
     */
    private static function config($plugin, $config_path = null)
    {
        if (!$config_path) {
            $config_path = Config::path('plugins', $plugin.'/plugin.json');
        }
        if (is_file($config_path)) {
            $json = file_get_contents($config_path);
            return json_decode($json, true);
        }
    }

    /**
     * Get or set plugin config in registry
     *
     * @return array
     */
    public static function registry($plugin = null)
    {
        if (!empty(self::$registry)) {
            return ($plugin)
                ? self::$registry[$plugin]
                : self::$registry;
        }
    }

    /**
     * Register a plugin helper
     *
     * @param  string $plugin
     * @return void
     */
    public static function helper($name, $function)
    {
        if ($plugin = self::$discover) {
            if (!isset(self::$registry[$plugin]['helpers'][$name])
                || self::$registry[$plugin]['helpers'][$name] !== false) {
                self::$registry[$plugin]['helpers'][$name] = true;
                Helper::register($name, $function);
            }
        } else {
            Helper::register($name, $function);
        }
    }

    public static function route()
    {
        /*
        Plugin::route(array(
            'match' => array(),
            'request' => array()
        ));
        */
    }

    public static function view()
    {
        /*
        Plugin::view(['get'], ['/uri-filter'], function ($arg)
        {
            // todo: how to get variable context?
        });
        */
    }

    public static function request()
    {
        // Should this be called Plugin::remote?
        /*
        Plugin::request(['get'], ['/uri-filter'], function ($request)
        {
            // this is to modify remote requests
            $request['data']['blah'] = 'test';
        });
        */
    }

    public static function dispatch()
    {
        // Should this be called Plugin::request or Plugin::middleware?
        /*
        Plugin::dispatch(['/uri-filter'], function ($request)
        {
            // this is to modify local requests
            // ...run after Request::route(), its like middleware!
            $request['template'] = 'awesome';
        });
        */
    }

    public static function error()
    {
        /*
        Plugin::error(function ($error)
        {
            // do something on framework error
        });
        */
    }

    public static function event()
    {
        /*
        Plugin::event('request.get', function ($input)
        {
            return $output;
        });
        */
    }
}