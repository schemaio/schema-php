<?php
/**
 * Forward // PHP Template Framework
 *
 * @version  1.0.2
 * @link 	 https://getfwd.com
 * @license  http://www.apache.org/licenses/LICENSE-2.0
 */

/* -----------------------------------------------------
 * Set framework file paths
 * -------------------------------------------------- */

$paths = array(

	// Base URI path, relative to server document root
	'uri' => '/',

	// Path to root directory (where index.php and config.php exist)
	'root' => __DIR__,

	// Path to core directory
	'core' => __DIR__.'/core',

	// Path to plugins directory
	'plugins' => __DIR__.'/plugins',

	// Path to templates directory
	'templates' => __DIR__.'/templates'
);

/* -----------------------------------------------------
 * Start the framework
 * -------------------------------------------------- */

require $paths['core'].'/start.php';
