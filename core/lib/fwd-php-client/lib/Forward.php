<?php

if (!function_exists('json_decode'))
{
	throw new Exception('Forward requires the JSON PHP extension');
}

require_once(dirname(__FILE__) . '/Forward/Cache.php');
require_once(dirname(__FILE__) . '/Forward/Client.php');
require_once(dirname(__FILE__) . '/Forward/Connection.php');
require_once(dirname(__FILE__) . '/Forward/Resource.php');
require_once(dirname(__FILE__) . '/Forward/Collection.php');
require_once(dirname(__FILE__) . '/Forward/Record.php');
