<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Event
{
    /**
     * Registry of event bindings
     * @var array
     */
    private static $events;

    /**
     * Flag to stop event chain
     * @var bool
     */
    private static $stop;

    /**
     * Bind callback to an event
     *
     * @param  string $target
     * @param  string $event
     * @param  closure $callback
     * @param  int $level
     * @return bool
     */
    public static function bind($target, $event, $callback = null, $level = 1)
    {
        if ($callback === null) {
            $callback = $event;
            $event = $target;
            $target = 0;
        }

        if (!is_callable($callback)) {
            return false;
        }

        $events = self::parse_bind_events($target, $event);
        foreach ($events as $event) {
            $key = $event['key'];
            $pre = $event['pre'];
            $name = $event['name'];

            // Make sure it's only bound once
            if (!isset(static::$events[$key][$pre][$name][$level])) {
                static::$events[$key][$pre][$name][$level] = array();
            }
            foreach (static::$events[$key][$pre][$name] as $event_level) {
                foreach ($event_level as $ex_callback) {
                    if ($ex_callback === $callback) {
                        return false;
                    }
                }
            }

            // "Bind" the callback
            static::$events[$key][$pre][$name][$level][] = $callback;

            // Sort levels.
            ksort(static::$events[$key][$pre][$name]);
        }

        return true;
    }

    /**
     * Bind event formatter
     *
     * @param  string $target
     * @param  string $event
     * @return array
     */
    public static function parse_bind_events($target, $event)
    {
        // Event arg optionally combined with target
        if ($event === null) {
            $event = $target;
            $target = 0;
        } else {
            // Convert object to class string
            if ((object)$target === $target) {
                $target = get_class($target);
            }
            // Target is case insensitive
            if (is_string($target)) {
                $target = strtolower($target);
            }
        }

        // Event format is an Array or string of
        // [target.][pre:]event[,[pre:]event]
        $event_parts = (array)$event === $event ? $event : explode(',', $event);
            
        foreach ($event_parts as $event) {
            $event = strtolower($event);
            $event = str_replace(' ', '', $event);

            // Target as part of event string?
            if ($target === 0) {
                // Target specified before '.'
                $target_parts = explode('.', $event);

                // Combine remaining '.' into event string
                if ($target_parts[1]) {
                    $key = array_shift($target_parts);
                    $event = implode('.', $target_parts);
                } else {
                    $key = $target;
                    $event = $target_parts[0];
                }
            } else {
                // Target as event key
                $key = $target;
            }

            // Determine pre value
            $pre_parts = explode(':', $event);
            if (count($pre_parts) > 1) {
                $name = $pre_parts[1];
                $pre = $pre_parts[0];
            } else {
                $name = $pre_parts[0];
                $pre = 'on';
            }

            // Save parsed event
            $parsed_events[] = array(
                'key' => $key,
                'pre' => $pre,
                'name' => $name
            );
        }

        return $parsed_events;
    }

    /**
     * Return value from bind callback and cancel trigger chain
     *
     * @param  mixed $result
     * @return mixed
     */
    public static function stop($result = null)
    {
        self::$stop = true;
        return $result;
    }

    /**
     * Trigger event bindings
     *
     * @param  string $target
     * @param  string $event
     * @return mixed
     */
    public static function trigger($target, $event)
    {
        $args = array_slice(func_get_args(), 2);

        $events = self::parse_bind_events($target, $event);
        foreach ($events as $event) {
            $key = $event['key'];
            $pre = $event['pre'];
            $name = $event['name'];
            $result = count($args) ? $args[0] : 0;

            // If pre is 'on', trigger 'before' binds first
            if ($pre == 'on') {
                $pre_set = array('before', 'on');
            } else {
                $pre_set = array($pre);
            }

            // Reset cancel trigger
            self::$stop = false;

            // Trigger callback[s]
            foreach ($pre_set as $pre) {
                if (!isset(self::$events[$key][$pre][$name])) {
                    continue;
                }
                foreach ((array)self::$events[$key][$pre][$name] as $event_level) {
                    foreach ((array)$event_level as $callback) {
                        // TODO: use reflection to detect callback arg count
                        $return = call_user_func_array($callback, $args);
                        // Stop propagation?
                        if ($return === false) {
                            return false;
                        }
                        // Chain result
                        if (count($args)) {
                            $result = isset($return) ? ($args[0] = $return) : $args[0];
                        } else {
                            $result++;
                        }
                        // Stop chain?
                        if (self::$stop) {
                            self::$stop = false;
                            return $return;
                        }
                    }
                }
            }
        }
        if (empty($events)) {
            $result = count($args) ? $args[0] : 0;
        }

        return $result;
    }
}
