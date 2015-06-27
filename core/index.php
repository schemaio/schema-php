<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

/* -----------------------------------------------------
 * Start the output buffer
 * -------------------------------------------------- */

ob_start();

/* -----------------------------------------------------
 * Define Constants
 * -------------------------------------------------- */

define('EXT', ".php");
define('CRLF', "\r\n");

/* -----------------------------------------------------
 * Include Common Core Classes
 * -------------------------------------------------- */

require __DIR__.'/config.php';
require __DIR__.'/util.php';
require __DIR__.'/event.php';
require __DIR__.'/request.php';
require __DIR__.'/template.php';
require __DIR__.'/view.php';
require __DIR__.'/controller.php';
require __DIR__.'/helper.php';
require __DIR__.'/plugin.php';
require __DIR__.'/session.php';
require __DIR__.'/settings.php';

/* -----------------------------------------------------
 * Default framework file paths
 * -------------------------------------------------- */

if (!$GLOBALS['paths'])
{
    $root_dir = dirname(__DIR__);

    $GLOBALS['paths'] = array(

        // Base URI path, relative to server document root
        'uri' => '/',

        // Path to root directory (where index.php and config.php exist)
        'root' => $root_dir,

        // Path to core directory
        'core' => $root_dir.'/core',

        // Path to plugins directory
        'plugins' => $root_dir.'/plugins',

        // Path to templates directory
        'templates' => $root_dir.'/templates'
    );
}

/* -----------------------------------------------------
 * Load Config
 * -------------------------------------------------- */

Config::load();

