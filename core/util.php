<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema\Util;

/**
 * ArrayInterface enhances ArrayIterator access methods
 */
class ArrayInterface extends \ArrayIterator
{
    public function & __get($key)
    {
        $result =& $this[$key];
        return $result;
    }

    public function __set($key, $val)
    {
        return parent::offsetSet($key, $val);
    }

    public function offsetExists($key)
    {
        return $this->$key !== null;
    }

    public function offsetSet($key, $val)
    {
        parent::offsetSet($key, $val);
        $this->$key = $val;
    }

    public function dump($return = false)
    {
        return print_r($this->getArrayCopy(), $return);
    }
}

/**
 * Autoload classes according to PSR-0 standards
 *
 * @param  string $class_name
 * @return void
 */
function autoload($class_name)
{
    $class_name = ltrim($class_name, '\\');
    $class_path = "";

    if ($last_ns_pos = strripos($class_name, '\\')) {
        $namespace = substr($class_name, 0, $last_ns_pos);
        $class_name = substr($class_name, $last_ns_pos + 1);
        $class_path  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
    }

    $class_path .= str_replace('_', DIRECTORY_SEPARATOR, $class_name).EXT;

    // Require class to exist in core/lib
    $core_class_path = \Schema\Config::path('core', "/lib/{$class_path}");
    if (is_file($core_class_path)) {
        include $core_class_path;
    }
}

/**
 * Default error handler
 *
 * @param  int $code
 * @param  string $message
 * @param  string $file
 * @param  int $line
 * @param  array $globals
 * @param  Exception $exception
 * @return void
 */
function error_handler($code, $message, $file = "", $line = 0, $globals = null, $trace = null, $exception = false)
{
    // Hide errors if PHP is not set to report them
    if (!$exception) {
        $code = ($code & error_reporting());
        if (!$code) {
            return;
        }
    }

    error_log("App ".($exception ? 'Exception' : 'Error').": {$message} in {$file} on line {$line} (code: {$code})");

    if ($code == 404) {
        header('HTTP/1.1 404 Page Not Found');
    } else {
        header('HTTP/1.1 500 Internal Server Error');
    }

    if (!ini_get('display_errors')) {
        exit;
    }

    // Otherwise, continue to standard error handling...
    $type = $exception ? 'Exception' : 'Error';
    $type_code = $exception && $code ? ": {$code}" : '';
    switch ($code) {
        case E_ERROR:           $type_name = 'Error'; break;
        case E_WARNING:         $type_name = 'Warning'; break;
        case E_PARSE:           $type_name = 'Parse Error'; break;
        case E_NOTICE:          $type_name = 'Notice'; break;
        case E_CORE_ERROR:      $type_name = 'Core Error'; break;
        case E_CORE_WARNING:    $type_name = 'Core Warning'; break;
        case E_COMPILE_ERROR:   $type_name = 'Compile Error'; break;
        case E_COMPILE_WARNING: $type_name = 'Compile Warning'; break;
        case E_USER_ERROR:      $type_name = 'Error'; break;
        case E_USER_WARNING:    $type_name = 'Warning'; break;
        case E_USER_NOTICE:     $type_name = 'Notice'; break;
        case E_STRICT:          $type_name = 'Strict'; break;
        default:                $type_name = $exception ? get_class($exception) : 'Unknown';
    }

    $backtrace = $trace ?: debug_backtrace();
    array_shift($backtrace);

    if (isset($_SERVER['HTTP_HOST'])) {
?>
    <html>
    <head>
        <title>Application <?php echo $type; ?></title>
        <style>
            body {
                font: 16px Arial;

            }
            div.callStack {
                background-color: #eee;
                padding: 10px;
                margin-top: 10px;
            }
            i.message {
                color: #f00;
                white-space: normal;
                line-height: 22px;
            }
        </style>
    </head>
    <body>
        <h1>Application <?php echo $type; ?></h1>
        <ul>
            <li><b>Message:</b> (<?php echo $type_name; ?><?php echo $type_code; ?>) <pre><i class="message"><?php echo $message; ?></i></pre></li>
            <?php if ($file): ?>
                <li><b>File:</b> <?php echo $file; ?> on line <i><b><?php echo $line; ?></b></i></li>
            <?php endif;
            if (count($backtrace) > 1): ?>
                <li><b>Call Stack:</b>
                    <div class="callStack">
                        <ol>
                        <?php
                            foreach ($backtrace as $event) {
                                if ($event['function'] == 'trigger_error' || !isset($event['file'])) {
                                    continue;
                                }
                        ?>
                            <li>
                                <i><?php echo $event['function']; ?>()</i>
                                in <?php echo $event['file']; ?>
                                on line <i><b><?php echo $event['line']; ?></b></i>
                            </li>
                        <?php } ?>
                        </ol>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </body>
    </html>
<?php
    }
    else {
        print("{$type}: {$message}\n\n");
        if (count($backtrace) > 1) {
            foreach ($backtrace as $event) {
                print("    > {$event['function']} in {$event['file']} on line {$event['line']}\n");
            }
        }
    }

    die();
}

/**
 * Default exception handler
 *
 * @return void
 */
function exception_handler ($e)
{
    try {
        error_handler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, $e->getTrace(), $e);
    } catch (Exception $e) {
        print "Exception thrown by exception handler: '".$e->getMessage()."' on line ".$e->getLine();
    }
}

/**
 * Dump variables to string format
 *
 * @return string
 */
function dump ()
{
    foreach (func_get_args() as $var) {
        $val = (($var instanceof ArrayInterface) || ($var instanceof \Schema\Resource))
            ? $var->dump(true)
            : print_r($var, true);

        $dump[] = isset($val) ? $val : "NULL";
    }

    return $dump;
}

/**
 * Deep recursive merge multiple arrays
 *
 * @param  int|string @date
 * @return string
 */
function merge($set1, $set2)
{
    // TODO: make this work on any number of sets (func_get_args())
    $merged = $set1;

    if ((array)$set2 === $set2 || $set2 instanceof \ArrayIterator) {
        foreach ($set2 as $key => $value) {
            if (isset($merged[$key])) {
                if (((array)$value === $value || $value instanceof \ArrayIterator)
                    && ((array)$merged[$key] === $merged[$key] || $merged[$key] instanceof \ArrayIterator)) {
                    $merged[$key] = merge($merged[$key], $value);
                } elseif (isset($value)
                    && !((array)$merged[$key] === $merged[$key] || $merged[$key] instanceof \ArrayIterator)) {
                    $merged[$key] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }
    }

    return $merged;
}

/**
 * Determine if arg1 is contained in arg2
 *
 * @param  mixed $val_a
 * @param  mixed $val_b
 * @return bool
 */
function in($val_a, $val_b = null)
{
    if (is_scalar($val_a)) {
        if (is_array($val_b) || $val_b instanceof \Schema\Resource) {
            return in_array($val_a, (array)$val_b);
        } else if ($val_a && is_scalar($val_b)) {
            return strpos($val_b, $val_a) !== false;
        }
    } else if (is_array($val_a) || $val_a instanceof \Schema\Resource) {
        foreach ((array)$val_a as $k => $v) {
            if (!in($v, $val_b)) {
                return false;
            }
            return true;
        }
    }

    return false;
}

/**
 * Hyphenate a string
 *
 * @param  string $string
 * @return string
 */
function hyphenate($string)
{
    $string = trim($string);
    $string = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $string);
    $string = preg_replace('/[\_\s\-]+/', '-', $string);
    $string = preg_replace('/([a-z])([A-Z])/', '\\1-\\2', $string);
    $string = strtolower($string);

    return $string;
}

/**
 * Underscore a string
 *
 * @param  string $string
 * @return string
 */
function underscore($string)
{
    $string = trim($string);
    $string = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $string);
    $string = preg_replace('/[\_\s\-]+/', '_', $string);
    $string = preg_replace('/([a-z])([A-Z])/', '\\1-\\2', $string);
    $string = strtolower($string);

    return $string;
}

/**
 * Camelize a string
 *
 * @param  string $string
 * @return string
 */
function camelize($string)
{
    $string = preg_replace('/[-_]/', ' ', $string);
    $string = strtolower($string);
    $string = ucwords($string);
    $string = str_replace(' ', '', $string);

    return $string;
}

/**
 * Split hyphenated or underscored string into words
 *
 * @param  string $string
 * @return string
 */
function words($string)
{
    $string = preg_replace('/[-_]/', ' ', $string);
    $string = strtolower($string);
    $string = ucwords($string);

    return $string;
}

/**
 * Pluralize a string
 *
 * @param  string $string
 */
function pluralize($string, $if_many = null)
{
    // Conditional
    $prefix = '';
    $parts = null;
    if (is_numeric($string[0])) {
        $parts = explode(' ', $string);
        $if_many = array_shift($parts);
        $string = implode(' ', $parts);
        $prefix = $if_many.' ';
    } else if ($if_many) {
        $if_many = (is_array($if_many)) ? count($if_many) : $if_many;
    }

    if (isset($if_many) && $if_many == 1) {
        $string = singularize($string);
    } else {
        $plural = array(
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)ix|ex$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(buffal|tomat)o$/i' => '\1oes',
            '/(bu)s$/i' => '\1ses',
            '/(alias|status)/i'=> '\1es',
            '/(octop|vir)us$/i'=> '\1i',
            '/(ax|test)is$/i'=> '\1es',
            '/s$/i'=> 's',
            '/$/'=> 's'
        );
        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves'
        );
        $ignore = array(
            'equipment',
            'information',
            'rice',
            'money',
            'species',
            'series',
            'fish',
            'sheep',
            'data'
        );
        $lower_string = strtolower($string);
        foreach ($ignore as $ignore_string){
            if (substr($lower_string, (-1 * strlen($ignore_string))) == $ignore_string) {
                return $prefix.$string;
            }
        }
        foreach ($irregular as $_plural=> $_singular) {
            if (preg_match('/('.$_plural.')$/i', $string, $arr)) {
                return $prefix.preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $string);
            }
        }
        foreach ($plural as $rule => $replacement) {
            if (preg_match($rule, $string)) {
                return $prefix.preg_replace($rule, $replacement, $string);
            }
        }
    }

    return $prefix.$string;
}

/**
 * Singularize a string
 *
 * @param  string $string
 */
function singularize($string)
{
    if (is_string($string)) {
        $word = $string;
    } else {
        return false;
    }
    
    $singular = array (
        '/(quiz)zes$/i' => '\\1',
        '/(matr)ices$/i' => '\\1ix',
        '/(vert|ind)ices$/i' => '\\1ex',
        '/^(ox)en/i' => '\\1',
        '/(alias|status)es$/i' => '\\1',
        '/([octop|vir])i$/i' => '\\1us',
        '/(cris|ax|test)es$/i' => '\\1is',
        '/(shoe)s$/i' => '\\1',
        '/(o)es$/i' => '\\1',
        '/(bus)es$/i' => '\\1',
        '/([m|l])ice$/i' => '\\1ouse',
        '/(x|ch|ss|sh)es$/i' => '\\1',
        '/(m)ovies$/i' => '\\1ovie',
        '/(s)eries$/i' => '\\1eries',
        '/([^aeiouy]|qu)ies$/i' => '\\1y',
        '/([lr])ves$/i' => '\\1f',
        '/(tive)s$/i' => '\\1',
        '/(hive)s$/i' => '\\1',
        '/([^f])ves$/i' => '\\1fe',
        '/(^analy)ses$/i' => '\\1sis',
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\\1\\2sis',
        '/([ti])a$/i' => '\\1um',
        '/(n)ews$/i' => '\\1ews',
        '/s$/i' => ''
    );
    $irregular = array(
        'person' => 'people',
        'man' => 'men',
        'child' => 'children',
        'sex' => 'sexes',
        'move' => 'moves'
    );  
    $ignore = array(
        'equipment',
        'information',
        'rice',
        'money',
        'species',
        'series',
        'fish',
        'sheep',
        'press',
        'sms',
    );
    $lower_word = strtolower($word);
    foreach ($ignore as $ignore_word) {
        if (substr($lower_word, (-1 * strlen($ignore_word))) == $ignore_word) {
            return $word;
        }
    }
    foreach ($irregular as $singular_word => $plural_word) {
        if (preg_match('/('.$plural_word.')$/i', $word, $arr)) {
            return preg_replace('/('.$plural_word.')$/i', substr($arr[0],0,1).substr($singular_word,1), $word);
        }
    }
    foreach ($singular as $rule => $replacement) {
        if (preg_match($rule, $word)) {
            return preg_replace($rule, $replacement, $word);
        }
    }

    return $word;
}

/**
 * Order an array by index
 * Default ascending. Prefix with "!" for descending
 *
 *
 */
function sortby($array)
{
    if ($array instanceof \Schema\Collection) {
        $collection = $array;
        $array = $collection->records();
    } else if ($array instanceof \Schema\Record) {
        $record = $array;
        $array = $record->data();
    } else if (!is_array($array)) {
        return false;
    }

    $args = func_get_args();
    array_shift($args);

    $sorter = function ($a, $b = null)
    {
        static $args;

        if ($b == null) {
            $args = $a;
            return;
        }
        foreach ((array)$args as $k) {
            if ($k[0] == '!') {
                $k = substr($k, 1);
                if ($a[$k] === "" || $a[$k] === null) {
                    return 0;
                } else if (is_numeric($b[$k]) && is_numeric($a[$k])) {
                    return $a[$k] < $b[$k];
                }
                return strnatcmp(@$a[$k], @$b[$k]);
            } else {
                if ($b[$k] === "" || $b[$k] === null) {
                    if ($a[$k] === "" || $a[$k] === null) {
                        return 0;
                    }
                    return -1;
                } else if (is_numeric($b[$k]) && is_numeric($a[$k])) {
                    return $a[$k] > $b[$k];
                }
                return strnatcmp(@$b[$k], @$a[$k]);
            }
        }

        return 0;
    };

    $sorter($args);

    $array = array_reverse($array, true);
    uasort($array, $sorter);

    return $array;
}

/**
 * Convert a date to relative age string
 *
 * @param  int|string $date
 * @return string
 */
function age($date)
{
    $time = is_numeric($date) ? (int)$date : strtotime($date);
    $seconds_elapsed = (time() - $time);

    if ($seconds_elapsed < 60) {
        return 'just now';
    } else if ($seconds_elapsed >= 60 && $seconds_elapsed < 3600) {
        $num = floor($seconds_elapsed / 60);
        $age = pluralize("{$num} min");
    } else if ($seconds_elapsed >= 3600 && $seconds_elapsed < 86400) {
        $num = floor($seconds_elapsed / 3600);
        $age = pluralize("{$num} hour");
    } else if ($seconds_elapsed >= 86400 && $seconds_elapsed < 604800) {
        $num = floor($seconds_elapsed / 86400);
        $age = pluralize("{$num} day");
    } else if ($seconds_elapsed >= 604800 && $seconds_elapsed < 2626560) {
        $num = floor($seconds_elapsed / 604800);
        $age = pluralize("{$num} week");
    } else if ($seconds_elapsed >= 2626560 && $seconds_elapsed < 31536000) {
        $num = floor($seconds_elapsed / 2626560);
        $age = pluralize("{$num} month");
    } else if ($seconds_elapsed >= 31536000) {
        $num = floor($seconds_elapsed / 31536000);
        $age = pluralize("{$num} year");
    }

    return "{$age} ago";
}

/**
 * Convert date to relative age if outside of 'today'
 *
 * @param  int|string @date
 * @return string
 */
function age_date($date)
{
    if (!$time = strtotime($date)) {
        return '';
    }
    if (date('Y-m-d') == date('Y-m-d', $time) && ($time-5) < time()) {
        // Today past
        return age($date);
    } else if ($time > time() - 86400) {
        if (date('Y-m-d', $time) === date('Y-m-d')) {
            // Today future
            return 'Today '.date('g:i A', $time);
        }
        if (date('Y-m-d', $time) === date('Y-m-d', time() + 86400)) {
            // Tomorrow
            return date('M j g:i A', $time);
        }
    }
    if (date('Y', $time) === date('Y')) {
        // Past
        return date('M j', $time);
    } else {
        // Past year
        return date('M j, Y', $time);
    }
}

/**
 * Format number as localized currency string
 *
 * Options:
 * @param  string $amount Money value amount
 * @param  bool $format (Optional) Flag to display negative amount (default true)
 * @param  bool $negative (Optional) Flag to format amount with currency symbol and parantheses (default true)
 * @param  bool $locale (Optional) Locale identifier (default $globals.locale)
 * @param  bool $code (Optional) Currency ISO code (default $globals.currency)
 *
 * @return string
 */
function currency($params, $options = null)
{
    $amount = 0;
    $format = true;
    $negative = true;
    $code = isset($GLOBALS['currency']) ? $GLOBALS['currency'] : 'USD';
    $locale = isset($GLOBALS['locale']) ? $GLOBALS['locale'] : 'en_US';

    if (!is_array($params)) {
        $amount = $params;
    } else {
        if (isset($params['amount'])) {
            $amount = $params['amount'];
        }
        if (isset($params['format'])) {
            $format = $params['format'];
        }
        if (isset($params['negative'])) {
            $negative = $params['negative'];
        }
        if (isset($params['code'])) {
            $code = $params['code'];
        }
    }
    if (is_array($options)) {
        if (isset($options['format'])) {
            $format = $options['format'];
        }
        if (isset($options['negative'])) {
            $negative = $options['negative'];
        }
        if (isset($options['code'])) {
            $code = $options['code'];
        }
    }

    // Allow negative?
    $amount = ($negative || $amount > 0) ? $amount : 0;

    if (!is_numeric($amount)) {
        if (is_null($amount) || $amount === '') {
            $amount = 0;
        } else {
            return '!numeric';
        }
    }

    static $formatters;

    // Uses NumberFormatter if installed, falls back to localeconv with number_format
    if (class_exists('\NumberFormatter')) {
        if (!isset($formatters)) {
            $formatters = array();
        }
        if (!isset($formatters[$locale])) {
            $formatters[$locale] = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        }
    }

    if ($format) {

        if ($formatters) {
            // Use NumberFormatter
            $result = $formatters[$locale]->formatCurrency($amount, $code);
        } else {
            // Use localeconv
            $prevlocale = setlocale(LC_MONETARY, "0");
            $nextlocale = setlocale(LC_MONETARY, $locale);
            if ($nextlocale) {
                $result = money_format('%(i', floatval($amount));
                setlocale(LC_MONETARY, $prevlocale);
            } else {
                // Fall back to number format
                if ($amount < 0) {
                    // Nevative value
                    $result = '($'.number_format(abs(floatval($amount))).')';
                } else {
                    $result = '$'.number_format(floatval($amount));
                }
            }
        }
    } else {
        // No currency code format
        $result = floatval($amount);
        /* TODO: enable when API supports locale input number parsing
        // Use localeconv
        $prevlocale = setlocale(LC_MONETARY, "0");
        $nextlocale = setlocale(LC_MONETARY, $locale);
        if ($nextlocale) {
            $result = money_format('%!i', floatval($amount));
            setlocale(LC_MONETARY, $prevlocale);
        } else {
            // Fall back to number format
            $result = number_format(floatval($amount));
        }
        */
    }

    return $result;
}

/**
 * Format a JSON string by applying newlines and indentation
 *
 * @param  string $json
 * @param  string $indent (optional)
 * @return string
 */
function json_print($json, $indent = null)
{
    $indent = $indent ?: '    ';

    $result = '';
    $pos = 0;
    $newline = "\n";
    $prev_char = '';
    $out_of_quotes = true;

    // Auto convert to json string
    if (!is_string($json)) {
        // Note: will consider empty arrays as array vs object
        $json = json_encode($json);
    }

    // Unescape slashes
    $json = str_replace('\/', '/', $json);

    for ($i = 0; $i <= strlen($json); $i++) {
        $char = substr($json, $i, 1);

        if ($char == '"' && $prev_char != '\\') {
            $out_of_quotes = !$out_of_quotes;
        } else if (($char == '}' || $char == ']') && $out_of_quotes) {
            $result .= $newline;
            $pos--;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indent;
            }
        }

        $result .= $char;

        if (($char == ',' || $char == '{' || $char == '[') && $out_of_quotes) {
            $result .= $newline;
            if ($char == '{' || $char == '[') {
                $pos++;
            }
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indent;
            }
        }

        if (($char == ':') && $out_of_quotes) {
            $result .= ' ';
        }

        $prev_char = $char;
    }

    return $result;
}

/**
 * Evaluate conditional array agsinst value
 *
 * @param  array $conditions
 * @param  array $record
 * @return bool
 */
function eval_conditions($conditions, $value)
{
    $match = true;

    if (is_array($conditions)) {
        foreach ($conditions as $key => $compare) {
            if ($key && $key[0] === '$') {
                switch ($key) {
                case '$empty':
                    $match = empty($value);
                    break;
                case '$eq':
                    $match = ($value === $compare);
                    break;
                case '$ne':
                    $match = ($value !== $compare);
                    break;
                case '$lt':
                    $match = ($value < $compare);
                    break;
                case '$lte':
                    $match = ($value <= $compare);
                    break;
                case '$gt':
                    $match = ($value > $compare);
                    break;
                case '$gte':
                    $match = ($value >= $compare);
                    break;
                case '$or':
                    if (is_array($compare)) {
                        $match = false;
                        foreach ($compare as $compare_condition) {
                            $match = eval_conditions($compare_condition, $value);
                            if ($match) {
                                $match = true;
                                break;
                            }
                        }
                    } else {
                        $match = false;
                    }
                    break;
                case '$and': 
                    if (is_array($compare)) {
                        $match = true;
                        foreach ($compare as $compare_condition) {
                            $match = eval_conditions($compare_condition, $value);
                            if (!$match) {
                                $match = false;
                                break;
                            }
                        }
                    } else {
                        $match = false;
                    }
                    break;
                default:
                    $this_value = isset($value['']) ? $value[''] : null;
                    if (is_string($key) && strpos($key, '.') !== false) {
                        $parts = explode('.', $key);
                        foreach ($parts as $part) {
                            if (isset($this_value[$part])) {
                                $this_value = $this_value[$part];
                            } else {
                                $this_value = null;
                                break;
                            }
                        }
                    } else {
                        $this_value = isset($this_value[$key]) ? $this_value[$key] : null;
                    }
                    $match = eval_conditions($compare, $this_value);
                }
            } else {
                $this_value = $value;
                if (is_string($key) && strpos($key, '.') !== false) {
                    // Look up to parent record with '..'?
                    if (substr($key, 0, 2) === '..') {
                        $key = substr($key, 1);
                    }
                    $parts = explode('.', $key);
                    foreach ($parts as $part) {
                        if (isset($this_value[$part])) {
                            $this_value = $this_value[$part];
                        } else {
                            $this_value = null;
                            break;
                        }
                    }
                } else {
                    $this_value = isset($this_value[$key]) ? $this_value[$key] : null;
                }
                $match = eval_conditions($compare, $this_value);
            }
            if (!$match) {
                break;
            }
        }
    } else {
        $match = ($conditions == $value);
    }

    return $match;
}

/**
 * Evaluate a formula expression with scope data
 *
 * @param  array $expr
 * @param  array $data (optional)
 * @return bool
 */
function eval_formula($expression, $scope = null)
{
    // TODO: ...
}

/**
 * Process and cache an image file field, returning a local cache URL
 *
 * @param  array $params
 * - file      \Schema\Record object referencing a file\
 * - width     (optional) image width? default: original size
 * - height    (optional) image height? default: original size
 * - padded    (optional) pad image to avoid cropping? default: false
 * - anchor    (optional) anchor padded images to a certain side? left, right, top, bottom, default: null
 * - default   (optional) returns default string if image does not exist, default: false
 * - if_exists (optional) return image URL only if the file actually exists? default: true
 * @return string
 */
function image_url($params)
{
    if (isset($params['image'])) {
        foreach ((array)$params['image'] as $key => $val) {
            $params[$key] = $val;
        }
    }

    $file = isset($params['file']) ? $params['file'] : null;
    $width = isset($params['width']) ? $params['width'] : null;
    $height = isset($params['height']) ? $params['height'] : null;
    $padded = isset($params['padded']) ? $params['padded'] : null;
    $anchor = isset($params['anchor']) ? $params['anchor'] : null;
    $default = isset($params['default']) ? $params['default'] : null;
    $if_exists = isset($params['if_exists']) ? $params['if_exists'] : null;

    // File id and md5 required
    if (!isset($file['id']) || !isset($file['md5'])) {
        return;
    }

    // Build url path
    $id = "image.{$file['id']}";
    $name = "{$id}.{$file['md5']}";
    $url = $orig_url = rtrim(\Schema\Config::path('uri'), '/').'/core/cache/'.$name;
    if ($width || $height) $url .= ".{$width}x{$height}";
    if ($padded) $url .= ".padded";
    if ($anchor) $url .= ".{$anchor}";
    $orig_url .= ".jpg";
    $url .= ".jpg";

    // Build file path
    $path = \Schema\Config::path('root');
    $file_path = $path.$url;
    $orig_file_path = $path.$orig_url;

    // Ideally exists already
    if (is_file($file_path)) {
        return $url;
    }
    
    if (is_file($orig_file_path)) {
        // Already cached
        $src_image = imagecreatefromstring(file_get_contents($orig_file_path));
    } else {
        // Get file data directly from link to avoid bloating memory
        if (method_exists($file, 'link_url')) {
            $file_data_link = $file->link_url('data');
            $file_data = $file->client()->get($file_data_link);
        }
        if (!isset($file_data)) {
            // File does not exist
            if ($default || $if_exists !== false) {
                return $default ?: '';
            }

            // Return URL by default
            return $url;
        }

        // TODO: Need to clear existing image.id
        // ...

        // Convert image data from explicit or implicit base64 encoding
        if (is_array($file_data) && isset($file_data['$binary'])) {
            $src_data = base64_decode($file_data);
        } else if (is_string($file_data)) {
            $src_data = base64_decode($file_data);
        }
        if (!$src_data) {
            // Invalid format
            return $default ?: '';
        }

        // Create orig image from file data
        $src_image = imagecreatefromstring($src_data);
        if ($src_image === false) {
            // Psuedo error
            return "#/error-unsupported-image-type-or-format-or-corrupt{$url}";
        }

        // Save source file to local cache
        if (is_writeable(dirname($orig_file_path))) {
            imagejpeg($src_image, $orig_file_path, '86');
        } else {
            throw new \Exception("Unable to save image in ".dirname($orig_file_path)."/ (permission denied)");
        }
    }
        
    // Source dimensions
    $src_width = imagesx($src_image);
    $src_height = imagesy($src_image);
    
    // Proportional width or height?
    if (!$width) {
        $width = $src_width * ($height / $src_height);
    } else if (!$height) {
        $height = $src_height * ($width / $src_width);
    }

    /**
     * Begin image processing
     */
    $dest_width = $width ?: $src_width;
    $dest_height = $height ?: $src_height;

    // Create blank dest image of the requested size
    $dest_image = imagecreatetruecolor($dest_width, $dest_height);

    // Correct oddly shaped images
    $diff_width = $src_width - $dest_width;
    $diff_height = $src_height - $dest_height;

    // Do maths
    $dest_x = 0;
    $dest_y = 0;
    $ratio_x = ($src_height / $dest_height);
    $ratio_y = ($src_width / $dest_width);
    
    // Determine resize width, height position, with or without padding
    if (($padded && $ratio_y <= $ratio_x) || (!$padded && $ratio_x <= $ratio_y)) {
        $ratio = $ratio_x;
        $new_height = $dest_height;
        $new_width = round($src_width / $ratio);
        $dest_x = -(($new_width - $dest_width) / 2);
    } else {
        $ratio = $ratio_y;
        $new_width = $dest_width;
        $new_height = round($src_height / $ratio);
        $dest_y = -(($new_height - $dest_height) / 2);
    }

    // Anchor top, left, bottom, right?
    if (strpos($anchor, 'top') !== false) {
        $dest_y = 0;
    } else if (strpos($anchor, 'bottom') !== false) {
        $dest_y = $height - $new_height;
    }
    if (strpos($anchor, 'left') !== false) {
        $dest_x = 0;
    } else if (strpos($anchor, 'right') !== false) {
        $dest_x = $width - $new_width;
    }

    $white = imagecolorallocate($dest_image, 255, 255, 255); // white
    imagefilledrectangle($dest_image, 0, 0, $width, $height, $white); // fill the background

    // Resample the image to a new size
    imagecopyresampled($dest_image, $src_image, $dest_x, $dest_y, 0, 0, $new_width, $new_height, $src_width, $src_height);
    
    // Write image file to local cache
    if (is_writeable(dirname($file_path))) {
        // Write the image to the correct path
        imagejpeg($dest_image, $file_path, '100');
    } else {
        throw new \Exception("Unable to save image in ".str_replace('//', '/', dirname($file_path))."/ (permission denied)");
    }

    // Finally
    return $url;
}
