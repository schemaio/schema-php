<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Controller
{
    /**
     * Index of loaded classes
     * @var array
     */
    public static $classes;

    /**
     * Index of loaded instances
     * @var array
     */
    public static $instances;

    /**
     * Index of invoked method results
     * @var array
     */
    public static $results;

    /**
     * Load controller by name before invoking a method
     *
     * @param  string $name
     * @return array
     */
    public static function load($name)
    {
        $vars = Template::engine()->get();
        $controller = self::route($name, $vars['request']);

        if (!is_file($controller['path'])) {
            if (!is_file($controller['extend']['path'])) {
                throw new \Exception('Controller not found at '.($controller['path'] ?: 'undefined'));
            }
        }

        if (!isset(self::$classes)) {
            self::$classes = array();
            spl_autoload_register('\\Schema\\Controller::autoload');
        }

        $class = "{$controller['namespace']}\\{$controller['class']}";
        if (!isset(self::$classes[$class])) {
            self::$classes[$class] = $controller;
        }

        if (!class_exists($class)) {
            throw new \Exception($controller['class'].' not defined in '.$controller['path']);
        }

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }

        $controller['instance'] = self::$instances[$class];

        return $controller;
    }

    /**
     * Invoke a controller class/method
     *
     * @param  string $name
     * @param  array $params
     * @return void
     */
    public static function invoke($name, $params = null)
    {
        $controller = self::load($name);
        $instance = $controller['instance'];
        
        $method = null;
        if (isset($controller['method'])) {
            $method = $controller['method'];
        } else if (property_exists($instance, 'default')) {
            $method = $instance->default;
        }

        $vars = Template::engine()->get();
        foreach ((array)$params as $var => $value) {
            $vars[$var] = $value;
        }
        foreach ((array)$vars as $var => $value) {
            $instance->{$var} = $value;
        }
        if (isset($method)) {
            if (method_exists($instance, $method)) {
                $class_method = $controller['class'].$method;
                if (!array_key_exists($class_method, (array)self::$results)) {

                    call_user_func_array(array($instance, $method), array());
                    foreach ((array)$instance as $var => $value) {
                        $vars[$var] = $value;
                    }
                    
                    self::$results[$class_method] = true;
                }
            } else {
                if ($controller['method']) {
                    throw new \Exception("Controller method '".$method."()' not defined in ".$controller['class']);
                }
            }
        }
        foreach ((array)$instance as $var => $value) {
            $vars[$var] = $value;
        }

        Template::engine()->set_global($vars);
    }

    /**
     * Route to a controller by name
     *
     * @param  string $name
     * @param  array $request
     */
    public static function route($name, $request)
    {
        $parts = explode('.', $name);

        $class_base_name = $parts[0];
        $class_base_path = $request['template_path'].'/controllers/';
        if (strpos($class_base_name, '/') !== false) {
            $path_parts = explode('/', ltrim($class_base_name, '/'));
            $class_base_name = array_pop($path_parts);
            $class_base_path = $class_base_path.implode('/', $path_parts).'/';
        }

        $class_name = Util\camelize($class_base_name).'Controller';
        $class_method = isset($parts[1]) ? Util\underscore($parts[1]) : null;
        $class_file = $class_name.'.php';
        $class_path = $class_base_path.$class_file;

        $namespace = "Schema\\".Util\camelize($request['template'])."Template";
        $extend_namespace = isset($request['extend_template'])
            ? "Schema\\".Util\camelize($request['extend_template'])."Template" : null;
        $extend_class_path = isset($request['extend_template_path'])
            ? $request['extend_template_path'].'/controllers/'.$class_file : null;

        return array(
            'name' => $name,
            'namespace' => $namespace,
            'class' => $class_name,
            'file' => $class_file,
            'path' => $class_path,
            'method' => $class_method,
            'extend' => array(
                'namespace' => $extend_namespace,
                'path' => $extend_class_path
            )
        );
    }

    /**
     * Autoloader for template controllers
     *
     * @param  string $class_name
     */
    public static function autoload($class_name)
    {
        $controller = null;
        if (isset(self::$classes[$class_name])) {
            $controller = self::$classes[$class_name];
        }
        if ($controller && is_file($controller['path'])) {
            // Include all controllers in this path, and also extend paths
            foreach (array($controller['extend'], $controller) as $ctrl) {
                if (!$ctrl['path']) {
                    continue;
                }
                $base_path = str_replace($ctrl['file'], '', $ctrl['path']);
                foreach (glob(dirname($ctrl['path']).'/*Controller.php') as $controller_file_path) {
                    self::autoload_file($controller_file_path, $ctrl);
                    $this_file = str_replace($base_path, '', $controller_file_path);
                    $this_class = $ctrl['namespace'].'\\'.str_replace('.php', '', $this_file);
                    self::autoload_helpers($this_class);
                }
            }
        }
    }

    /**
     * Auto load and evaluate a controller from a file
     *
     * @param  string $controller_path
     * @param  array $controller
     */
    public static function autoload_file($controller_path, $controller)
    {
        $class_contents = file_get_contents($controller_path);

        // Auto append controller namespace
        $class_contents = preg_replace(
            '/<\?php/',
            '<?php namespace '.$controller['namespace'].';',
            $class_contents,
            1
        );

        ob_start();
        try {
            $result = eval('?>'.$class_contents);
        } catch (\Exception $e) {
            $e_class = get_class($e);
            $message = $controller['class'].': '.$e_class.' "'.$e->getMessage()
                .'" in '.$controller_path.' on line '.$e->getLine();
            throw new \Exception($message, $e->getCode(), $e);
        }
        ob_end_clean();

        if ($result === false) {
            $error = error_get_last();
            $message = 'Parse error: '.$error['message']
                .' in '.$controller_path.' on line '.$error['line'];
            $lines = explode("\n", htmlspecialchars($class_contents));
            $eline = $error['line']-1;
            $lines[$eline] = '<b style="background-color: #fffed9">'.$lines[$eline].'</b>';
            $first_line = $eline > 5 ? $eline-5 : 0;
            $lines = array_slice($lines, $first_line, 11);
            foreach ($lines as $k => $v) {
                $lines[$k] = ($eline-4+$k).' '.$v;
            }
            $content = implode("\n", $lines);
            $message .= "<pre>{$content}</pre>";
            throw new \Exception($message);
        }
    }

    /**
     * Auto load and register controller helpers
     *
     * @return void
     */
    public static function autoload_helpers($controller_class)
    {
        if (!property_exists($controller_class, 'helpers')) {
            return;
        }
        foreach ((array)$controller_class::$helpers as $key => $helper) {
            $helper_prop = is_numeric($key) ? $helper : $key;
            $helper_name = $helper;
            if (method_exists($controller_class, $helper_prop)) {
                Helper::register($helper_name, function() use($controller_class, $helper_prop)
                {
                    return forward_static_call_array(
                        array($controller_class, $helper_prop), func_get_args()
                    );
                });
            } else {
                throw new \Exception('Helper not declared at '.$controller_class.'::'.$helper_prop);
            }
        }
    }
}