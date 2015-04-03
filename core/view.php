<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class View
{
    /**
     * Route view path/args by request
     *
     * @param  array $request
     * @return array
     */
    public static function route($request)
    {
        $result = self::route_path($request);

        $request['view'] = $result['view'];
        $request['view_path'] = $result['path'];
        $request['base_path'] = $result['base'];
        $request['args'] = $result['args'];
        $request['output'] = $result['output'];

        return $request;
    }

    /**
     * Resolve a view request
     *
     * @param  array $request
     * @return array
     */
    public static function resolve($request)
    {
        $route_path = $request['path'];
        $template_path = $request['template_path'];
        $view_orig = isset($request['view']) ? $request['view'] : null;
        $view_required = isset($request['required']) ? $request['required'] : null;

        // Get output from view
        if (preg_match('/[^\/]+\.([^\/]+)$/', $route_path, $matches)) {
            $view_output = $matches[1];
            $view = substr($route_path, 0, strrpos($route_path, '.'));
        } else {
            $view = $route_path;
            $view_output = isset($request['output']) ? $request['output'] : 'html';
        }
        if ($view_output === 'php' || $view_output === 'tpl') {
            $view_output = 'html';
        }
        // Clean view paths
        if ($view) {
            $view = '/'.ltrim($view, '/');
        }
        if ($view_orig) {
            $view_orig = '/'.ltrim($view_orig, '/');
        }

        return array(
            'view' => $view,
            'output' => $view_output,
            'orig' => $view_orig,
            'required' => $view_required
        );
    }

    /**
     * Find a view by testing view uri parts
     *
     * @param  array $request
     * @param  bool $default
     * @return array
     */
    private static function route_path($request, $default = true)
    {
        $view = self::resolve($request);
        $view_output = $view['output'];
        $view_orig = $view['orig'];
        $template_path = $request['template_path'];
        $extend_template_path = isset($request['extend_template_path'])
            ? $request['extend_template_path'] : null;

        // Split view into parts
        $view_parts = explode('/', trim($view['view'], '/'));
        if ($view_parts[0] == null) $view_parts[0] = 'index';

        $view_path = "";
        $view_args = array();
        $short_tested = false;
        $index_dir_exists = is_dir("{$template_path}/views/index");
        foreach ($view_parts as $part) {
            $test_path = '/'.implode('/', $view_parts);

            // Try different view paths
            $views = array();
            if ($view_output === 'html') {
                array_push($views,
                    "{$test_path}.php",
                    "{$test_path}/index.php",
                    $index_dir_exists ? "/index{$test_path}.php" : '',
                    "{$test_path}.tpl",
                    "{$test_path}/index.tpl",
                    $index_dir_exists ? "/index{$test_path}.tpl" : ''
                );
            } else {
                array_push($views,
                    "{$test_path}.{$view_output}.php",
                    "{$test_path}/index.{$view_output}.php",
                    $index_dir_exists ? "/index{$test_path}.{$view_output}.php" : '',
                    "{$test_path}.{$view_output}.tpl",
                    "{$test_path}/index.{$view_output}.tpl",
                    $index_dir_exists ? "/index{$test_path}.{$view_output}.tpl" : ''
                );
            }
            array_push($views,
                "{$test_path}.{$view_output}",
                "{$test_path}/index.{$view_output}",
                $index_dir_exists ? "/index{$test_path}.{$view_output}" : ''
            );

            // Try hidden paths for nested views
            if (Template::engine()->depth() > 0) {
                $test_path_hidden = preg_replace('/\/([^\/]+)$/', '/_$1', $test_path);
                array_push($views, "{$test_path_hidden}.{$view_output}");
                if ($index_dir_exists) {
                    array_push($views, "/index{$test_path_hidden}.{$view_output}");
                }
            }

            $found = false;
            foreach ($views as $view) {
                if (empty($view)) {
                    continue;
                }
                $view_path = "{$template_path}/views{$view}";
                if (is_file($view_path) && ($view_orig ? $view_orig === $view : !$view_orig)) {
                    $found = true;
                    break(2);
                }
                if ($extend_template_path) {
                    $view_path = "{$extend_template_path}/views{$view}";
                    if (is_file($view_path) && ($view_orig ? $view_orig === $view : !$view_orig)) {
                        $found = true;
                        break(2);
                    }
                }
            }

            // Short cut in case of default view with arguments
            if ($short_tested === false) {
                $short_tested = true;
                $dir_path = "{$template_path}/views/{$part}";

                // If base path does not exist at all, skip checking all arg parts
                if (!is_dir($dir_path) && !count(glob($dir_path.'*'))) {
                    if ($extend_template_path) {
                        $dir_path = "{$extend_template_path}/views/{$part}";
                        if (!is_dir($dir_path)) {
                            $view_args = $view_parts;
                            break;
                        }
                    } else {
                        $view_args = $view_parts;
                        break;
                    }
                }
            }

            // Put test part in args
            $arg_part = array_pop($view_parts);
            array_unshift($view_args, $arg_part);
        }

        // If not found, return original assumed view path
        if ($found === false) {
            // Try default view, as a last resort
            if ($default) {
                $default_views = array();
                if ($view_output === 'html') {
                    array_push($default_views,
                        "/default.php",
                        "/default.tpl"
                    );
                } else if ($view_output !== 'php') {
                    array_push($default_views,
                        "/default.{$view_output}.php",
                        "/default.{$view_output}.tpl"
                    );
                }
                array_push($default_views,
                    "/default.{$view_output}"
                );
                foreach ($default_views as $default_view) {
                    $default_view_path = "{$template_path}/views{$default_view}";
                    if (is_file($default_view_path)) {
                        $view = $default_view;
                        $view_path = $default_view_path;
                        break;
                    } else if ($extend_template_path) {
                        $default_view_path = "{$extend_template_path}/views{$default_view}";
                        if (is_file($default_view_path)) {
                            $view = $default_view;
                            $view_path = $default_view_path;
                            break;
                        }
                    }
                    $view = $view_orig ?: $views[3];
                    $view_path = "{$template_path}/views{$view}";
                }
            } else {
                $view = $view_orig;
                $view_path = "{$template_path}/views{$view}";
            }
        }

        // Base path is the template view directory
        $base_path = str_replace("{$template_path}/views", '', dirname($view_path));

        return array(
            'view' => $view,
            'path' => $view_path,
            'base' => $base_path,
            'args' => $view_args,
            'output' => $view_output
        );
    }

    /**
     * Render a request view with layout
     *
     * @param  array $request
     * @return string
     */
    public static function render($request)
    {
        $vars = array(
            'request' => &$request,
            'params' => Request::params(),
            'session' => Request::session()
        );

        $content = self::render_content($request, $vars);
        if (is_int($content)) {
            return $content;
        }
        $content = self::render_layout($content, $request, $vars);

        return $content;
    }

    /**
     * Render view content
     *
     * @param  array $request
     * @param  array $vars
     * @return string
     */
    private static function render_content($request, &$vars)
    {
        return Template::engine()->render($request['view_path'], $vars);
    }

    /**
     * Render layout with view content
     *
     * @param  string $content
     * @param  array $request
     * @return string
     */
    private static function render_layout($content, $request, $vars)
    {
        if (array_key_exists('layout', $request) && !$request['layout']) {
            return $content;
        }

        if (!is_dir($request['template_path'].'/views/layouts/')
            && (!isset($request['extend_template_path'])
                || !is_dir($request['extend_template_path'].'/views/layouts/'))) {
            return $content;
        }

        $default = $request['ajax'] ? 'ajax' : 'default';
        $layout = isset($request['layout']) ? $request['layout'] : $default;

        // Try different layout file paths
        $layout_files = array();
        if ($request['output'] === 'html') {
            array_push($layout_files,
                "{$layout}.php",
                "{$layout}.tpl"
            );
        } else {
            array_push($layout_files,
                "{$layout}.{$request['output']}.php",
                "{$layout}.{$request['output']}.tpl"
            );
        }

        $layout_found_path = null;
        foreach ($layout_files as $layout_file) {
            $layout_path = $request['template_path'].'/views/layouts/'.$layout_file;
            $extend_layout_path = isset($request['extend_template_path'])
                ? $request['extend_template_path'].'/views/layouts/'.$layout_file : null;
            if (is_file($layout_path)) {
                $layout_found_path = $layout_path;
                break;
            }
            if (is_file($extend_layout_path)) {
                $layout_found_path = $extend_layout_path;
                break;
            }
        }
        if (!$layout_found_path) {
            if ($layout !== $default) {
                throw new \Exception("Layout not found at {$layout_path}");
            } else {
                return $content;
            }
        }
    
        $vars['content_for_layout'] = $content;
        return Template::engine()->render($layout_found_path, $vars);
    }

    /**
     * Execute view logic conditionally based on request state
     *
     * @param  string $method
     * @param  array $request_match optional
     * @param  closure $callback
     * @return mixed
     */
    public static function on($method, $request_match, $callback = null)
    {
        // TODO: on() should auto-defer callback execution until request is run (or if in middle of one)
        $request = Template::engine()->get('request');

        if (strcasecmp($request['method'], $method) != 0) {
            return;
        }
        if (is_callable($request_match)) {
            $callback = $request_match;
            $request_match = array();
        }
        $result = Request::route($request, array(
            array(
                'match' => $request_match,
                'request' => array('matched' => true)
            )
        ));
        if (!$result['matched']) {
            return null;
        }

        $info = new \ReflectionFunction($callback);
        $args = array_pad($request['args'], $info->getNumberOfParameters(), null);
        $vars = Template::engine()->get();
        $args = array_unshift($vars);

        $result = call_user_func_array($callback, $args);

        Template::engine()->set($vars);
        Template::engine()->result($result);
    }
}